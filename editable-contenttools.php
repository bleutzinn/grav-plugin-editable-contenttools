<?php
namespace Grav\Plugin;

use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class EditableContentToolsPlugin
 * @package Grav\Plugin
 */
class EditableContentToolsPlugin extends Plugin
{

    protected $my_name = 'editable-contenttools';
    protected $my_full_name = 'Editable ContentTools';
    protected $token = 'editable-contenttools-api';

    /**
     * Add Editor code and styles
     */
    public function addAssets()
    {
        // Get assets objects
        $assets = $this->grav['assets'];

        // Add styles
        $assets->addCss('plugin://' . $this->my_name . '/vendor/content-tools.min.css', 1);
        $assets->addCss('plugin://' . $this->my_name . '/css/editor.css', 1);

        // Add code
        $assets->addJs('plugin://' . $this->my_name . '/vendor/turndown.js', 1);
        $assets->addJs('plugin://' . $this->my_name . '/vendor/content-tools.min.js', 1);
        $assets->AddJs('https://unpkg.com/turndown-plugin-gfm/dist/turndown-plugin-gfm.js', 1);

        // Add reference to dynamically created assets
        $route = $this->grav['page']->route();
        if ($route == '/') {
            $route = '';
        }
        //$assets->addJs($this->my_name . $route . '/editor.js', ['group' => 'bottom']);
        $assets->addJs($this->my_name . '-api' . $route . '/editor.js', ['group' => 'bottom']);
    }

    /**
     * This will execute $cmd in the background (no cmd window)
     * without PHP waiting for it to finish, on both Windows and Unix.
     * http://php.net/manual/en/function.exec.php#86329
     * 
     * Not tested on Windows by plugin dev
     * 
     */
    public function execInBackground($cmd) { 
        if (substr(php_uname(), 0, 7) == "Windows"){ 
            pclose(popen("start /B ". $cmd, "r"));  
        } 
        else { 
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
     * Add Editor to the page
     */
    public function onPageInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        if ($this->userAuthorized()) {
            $this->addAssets();
        }
    }

    /**
     * Pass valid actions (via AJAX requests) on to the editor resource to handle
     *
     * @return the output of the editor resource
     */
    public function onPagesInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        $paths = $this->grav['uri']->paths();
        
        if (array_shift($paths) == $this->token) {
            $target = array_pop($paths);
            $route = implode('/', $paths);
            
            switch ($target) {

                case 'editor.js': // Return editor instantiation as Javascript
                    $nonce = Utils::getNonce($this->my_name . '-nonce');

                    // Create absolute URL including token and action
                    $save_url = $this->grav['uri']->rootUrl(true) . '/' . $this->token . '/' . $route . '/save';
                    // Render the template
                    $output = $this->grav['twig']->processTemplate('editor.js.twig', [
                        'save_url' => $save_url,
                        'nonce' => $nonce,
                    ]);

                    $this->setHeaders('text/javascript');
                    echo $output;
                    exit;

                case 'save':
                    if ($_POST) {
                        $this->saveRegions('/' . $route);
                    }

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
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
        ]);
    }

    /**
     * Register custom shortcode
     */
    public function onShortcodeHandlers()
    {
        $this->grav['shortcode']->registerShortcode('EditableContentToolsShortcode.php', __DIR__ . '/shortcodes/');
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
    public function saveRegions($route)
    {
        $result = false;
        $post = $_POST;
        $nonce = $post['ct-nonce'];

        if (Utils::verifyNonce($nonce, $this->my_name . '-nonce')) {
            $page = $this->grav['pages']->find($route);
            $content = $page->rawMarkdown();

            foreach ($post as $key => $value) {

                // Wrap Markdown in newlines (important!)
                $value = PHP_EOL . $value . PHP_EOL;

                // Replace each shortcode content
                if (preg_match('/\[editable .*?name=[\'"]' . $key . '[\'"].*?\](.*?)\[\/editable\]/is', $content, $matches) == 1) {
                    $content = str_replace($matches[1], $value, $content);
                }
            }

            // Do the actual save action
            $page->rawMarkdown($content);
            $page->save();
            
            // Trigger Git Sync
            $config = $this->grav['config'];
            if ($config->get('plugins.git-sync.enabled') &&
                $config->get('plugins.editable-contenttools.git-sync')) {
                
                $command = GRAV_ROOT . '/bin/plugin git-sync sync';
                
                $this->execInBackground($command);
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
