<?php
namespace Grav\Plugin;

use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class EditableContentToolsPlugin
 * @package Grav\Plugin
 */
class EditableContentToolsPlugin extends Plugin
{

    protected $plugin_name = 'editable-contenttools';
    protected $plugin_token = 'editable-contenttools-api';

    /**
     * Add Editor code and styles
     */
    public function addAssets()
    {
        // Get assets objects
        $assets = $this->grav['assets'];

        // Add styles
        $assets->addCss('https://cdn.jsdelivr.net/npm/ContentTools@1.6.12/build/content-tools.min.css');
        $assets->addCss('plugin://' . $this->plugin_name . '/css/editor.css', 1);

        // Add code
        //$assets->addJs('plugin://' . $this->plugin_name . '/vendor/turndown.js');
        $assets->addJs('https://cdn.jsdelivr.net/npm/turndown@7.0.0/dist/turndown.js');
        $assets->addJs('https://cdn.jsdelivr.net/npm/ContentTools@1.6.12/build/content-tools.min.js');
        //$assets->AddJs('plugin://' . $this->plugin_name . '/vendor/turndown-plugin-gfm.js');
        $assets->AddJs('https://cdn.jsdelivr.net/npm/turndown-plugin-gfm@1.0.2/dist/turndown-plugin-gfm.js');

        // Add reference to dynamically created asset editor.js
        $route = $this->grav['uri']->baseIncludingLanguage() . $this->grav['uri']->route();
        $path = explode('/', ltrim($route, '/'));
        if ($this->grav['uri']->base() != $this->grav['uri']->rootUrl(true)) {
            array_shift($path);
        }
        $route = '/' . implode('/', $path);
        $assets->addJs(Uri::cleanPath($this->plugin_name . '-api' . $route . 'editor.js'));
    }

    /**
     * This will execute $cmd in the background (no cmd window)
     * without PHP waiting for it to finish, on both Windows and Unix.
     * http://php.net/manual/en/function.exec.php#86329
     *
     * Not tested on Windows by plugin dev
     *
     */
    public function execInBackground($cmd)
    {
        if (strtolower(substr(php_uname('s'), 0, 3)) == "win") {
            pclose(popen("start /B " . $cmd, "r"));
        } else {
            exec($cmd . " > /dev/null &");
        }
    }

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * When a user is authorized preprocess editable region shortcodes
     * and add Editor to the page
     * In case the user is not authorized remove shortcode tags
     */
    public function onPageInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        $page = $this->grav['page'];
        $content = $page->rawMarkdown();

        if ($this->userAuthorized()) {

            // Check shortcode names
            // Insert when missing: [editable] | [editable name=""]
            // Renumber existing "reserved" values, e.g.: [editable name="region-3"]
            $re = '/((\[editable)(( +name="(region-[0-9]*)*") *\]|\]))/is';
            preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0);

            if (count($matches) > 0) {
                $i = 0;
                foreach ($matches as $match) {

                    // Insert or replace name parameter
                    $pos = strpos($content, $match[0]);
                    if ($pos !== false) {
                        $content = substr_replace($content, '[editable name="region-' . $i . '"]', $pos, strlen($match[0]));
                    }
                    $i++;
                }

                // If names were changed save the page
                if ($i > 0) {
                    // Do the actual save action
                    $page->rawMarkdown($content);
                    $page->save();
                    $this->grav['pages']->dispatch($page->route());
                }
            }

            // Process shortcodes by parsing to HTML avoiding Twig and Parsedown Extra
            $re = '/\[editable name="(.*?)"\](.*?)\[\/editable\]/is';
            preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0);

            if (count($matches) > 0) {

                $parsedown = new \Parsedown();
                foreach ($matches as $match) {
                    $find = $match[0];
                    $html = $parsedown->text($match[2]);
                    $replace = '<div data-editable data-name="' . $match[1] . '">' . $html . '</div>';
                    $content = str_replace($find, $replace, $content);
                }

                $page->rawMarkdown($content);

                $this->addAssets();

                // Update the current Page object content
                // The call to Page::content() recaches the page. If not done a browser
                // page refresh is required to properly initialize the ContentTools editor
                unset($this->grav['page']);
                $this->grav['page'] = $page;
                $this->grav['page']->rawMarkdown($page->rawMarkdown());
                $this->grav['page']->content($page->content());

            }
        } else {

            // Remove all shortcodes
            $re = '/\[editable( name=".*?")?\](.*?)\[\/editable\]/mis';
            preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0);

            if (count($matches) > 0) {

                foreach ($matches as $match) {
                    $find = $match[0];
                    $replace = $match[2];
                    $content = str_replace($find, $replace, $content);
                }

                $page->rawMarkdown($content);
            }
        }
    }

    /**
     * Pass valid actions (via AJAX requests) on to the editor resource to handle
     *
     * @return mixed
     */
    public function onPagesInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        if(isset($_POST['action']) && $_POST['action'] == 'save')
        {
            $this->saveRegions();
            exit;
        }

        $paths = $this->grav['uri']->paths();

        // Check whether action is required here
        if (array_shift($paths) == $this->plugin_token) {
            $target = array_pop($paths);
            $route = implode('/', $paths);

            switch ($target) {

                case 'editor.js': // Return editor instantiation as Javascript
                    $nonce = Utils::getNonce($this->plugin_name . '-nonce');

                    // Create absolute save URL including token and action
                    $save_url = $this->grav['uri']->rootUrl(true) . $this->grav['uri']->referrer();
                    // Render the template
                    $output = $this->grav['twig']->processTemplate('editor.js.twig', [
                        'save_url' => $save_url,
                        'nonce' => $nonce,
                    ]);

                    $this->setHeaders('text/javascript');
                    echo $output;
                    exit;

                default:
                    return;
            }
        }
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the events we are interested in
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ]);
    }

    /**
     * Add current directory to Twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        // Add local templates folder to the Twig templates search path
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Save each region content to it's corresponding shortcode
     */
    public function saveRegions()
    {
        $result = false;
        $nonce = $_POST['ct-nonce'];

        if (Utils::verifyNonce($nonce, $this->plugin_name . '-nonce')) {

            $page = $this->grav['page'];
            $content = $page->rawMarkdown();

            foreach ($_POST as $key => $value) {
                // Replace each shortcode content
                if (preg_match('/\[editable name="' . $key . '"\](.*?)\[\/editable\]/is', $content, $matches) == 1) {
                    $find = $matches[0];
                    $replace = '[editable name="' . $key . '"]' . $value . '[/editable]';
                    $content = str_replace($find, $replace, $content);
                }
            }

            // Do the actual save action
            $page->rawMarkdown($content);
            $page->save();

            // Trigger Git Sync
            $config = $this->grav['config'];

            if ($config->get('plugins.git-sync.enabled') &&
                $config->get('plugins.editable-contenttools.git-sync')) {
                if ($config->get('plugins.editable-contenttools.git-sync-mode') == 'background') {

                    $command = GRAV_ROOT . '/bin/plugin git-sync sync';
                    $this->execInBackground($command);

                } else {

                    $this->grav->fireEvent('gitsync');

                }

            }

            exit;
        }

        // Saving failed
        // Create a custom error page
        // BTW the HTTP status code is set via the page frontmatter
        $page = new Page;
        $page->init(new \SplFileInfo(__DIR__ . '/pages/save-error.md'));

        // Let Grav return the error page
        unset($this->grav['page']);
        $this->grav['page'] = $page;

    }

    /**
     * Set return header
     *
     * @return header
     */
    public function setHeaders($type = 'application/json')
    {
        header('Content-type: ' . $type);

        // Calculate Expires Headers if set to > 0
        $expires = $this->grav['config']->get('system.pages.expires');
        if ($expires > 0) {
            $expires_date = gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';
            header('Cache-Control: max-age=' . $expires);
            header('Expires: ' . $expires_date);
        }
    }

    /**
     * Check that the user is permitted to edit
     * Requires site and editable permissions in user account file:
     * access:
     *   site:
     *     login: true
     *     editable: true
     *
     * @return boolean
     */
    public function userAuthorized()
    {
        $result = false;
        $user = $this->grav['user'];

        if ($user->authorized) {
            $result = $user->authorize('site.editable') || $user->authorize('admin.super') || $user->authorize('admin.pages');
        }
        return $result;
    }

}