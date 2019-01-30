<?php
namespace Grav\Plugin;

use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use Grav\Common\Utils;
use Grav\Common\Twig\Twig;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class EditableContentToolsPlugin
 * @package Grav\Plugin
 */
class EditableContentToolsPlugin extends Plugin
{

    protected $my_name = 'editable-contenttools';
    protected $my_full_name = 'Editable ContentTools';
    public $sc_ids = [];

    /**
     * Add Editor code and styles
     */
    public function addAssets()
    {
        // Get assets objects
        $assets = $this->grav['assets'];

        if ($this->user_authorized) {
            // Add editor assets
            // Add styles
            $assets->addCss('plugin://' . $this->my_name . '/vendor/content-tools.min.css', 1);
            $assets->addCss('plugin://' . $this->my_name . '/css/editor.css', 1);
            // Add code
            //$assets->AddJs('https://unpkg.com/turndown/dist/turndown.js', 1);
            $assets->addJs('plugin://' . $this->my_name . '/vendor/turndown.js', 1);
            $assets->AddJs('https://unpkg.com/turndown-plugin-gfm/dist/turndown-plugin-gfm.js', 1);
            $assets->addJs('plugin://' . $this->my_name . '/vendor/content-tools.min.js', 1);
            // Add reference to dynamically created initialisation code
            // (to be created in $this->onPagesInitialised())
            $assets->addJs($this->my_name . $this->grav['page']->route() . '/editor.js', ['group' => 'bottom']);
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
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Handle resource requests
     * 
     * @return mixed
     */
    public function handleResource($page)
    {
        $paths = $this->grav['uri']->paths();
        // Check whether the requested page name equals this plugin name
        if (array_shift($paths) == $this->my_name) {
            $asset = array_pop($paths);
            $slug = array_pop($paths);
            $route = implode('/', $paths);
            $target_page = $this->grav['page']->find($route . '/' . $slug);
            switch ($asset) {
                case 'editor.js': // Return editor instantiation as Javascript
                    $nonce = Utils::getNonce($this->my_name . '-nonce');
                    // Render the template
                    $output = $this->grav['twig']->processTemplate('editor.js.twig', [
                        'save_url' => ($this->my_name . $target_page->route() . '/save'),
                        'nonce' => $nonce
                    ]);
                    $this->setHeaders('text/javascript');
                    echo $output;
                    exit;
                case 'save':
                    $this->saveChanges();
                default:
                    return;
            }
        }
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

        $page = $this->grav['page'];
        
        // Change this normal! page into an editable one when user is permitted
        if ($page->modular()) {
            $this->grav['log']->warning($this->my_full_name . ': can\'t act on modular pages');
            $this->grav['debugger']->addMessage($this->my_full_name . ' can\'t act on modular pages');
            return;
        }
        else {
            $config = $this->mergeConfig($page);
            $user = $this->grav['user'];
            $username = $user->get('username');
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

        if (!$this->user_authorized) {
            return;
        }

        $page = $this->grav['page'];
        if (!$page->exists) {
            $this->handleResource($page);
        }
        else {
            if ($page->modular()) {
                $this->grav['log']->warning($my_full_name . ' can\'t act on modular pages');
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

        // Check for user authorisation
        $this->user_authorized = $this->userIsAuthorized($this->grav['page'], $this->grav['user']);

        // Abort when the user is not authorized
        if (!$this->user_authorized) {
            return;
        }

        // Enable the events we are interested in
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ]);
    }

    /**
     * Register custom shortcode
     */
    public function onShortcodeHandlers()
    {
        $this->grav['shortcode']->registerShortcode('EditableContentToolsShortcode.php' , __DIR__.'/shortcodes/');
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
    public function saveChanges()
    {
        $result = false;
        $post = $_POST;
        // Do not act upon empty POST payload
        if ($post) {
            $nonce = $post['ct-nonce'];
            if (Utils::verifyNonce($nonce, $this->my_name . '-nonce')) {
                $paths = $this->grav['uri']->paths();
                // Check whether the requested page name equals this plugin name
                if (array_shift($paths) == $this->my_name) {
                    $asset = array_pop($paths);
                    $slug = array_pop($paths);
                    $route = implode('/', $paths);
                    $page = $this->grav['page']->find($route . '/' . $slug);
                    $content = $page->rawMarkdown();
                    array_shift($post);
                    foreach($post as $key => $value){
                        // Wrap Markdown in newlines (important!)
                        $value = PHP_EOL . $value . PHP_EOL;
                        preg_match('/\[editable .*?name=[\'"]' . $key . '[\'"].*?\](.*?)\[\/editable\]/is', $content, $matches);
                        $content = str_replace($matches[1], $value, $content);
                    }
                    $page->rawMarkdown($content);
                    // Do the actual save action
                    $page->save();
                    exit;
                }
            }
        }
        // Saving failed        
        if (!$result) {
            // Create a custom error page
            // BTW the HTTP status code is set in that page frontmatter
            $page = new Page;
            $page->init(new \SplFileInfo(__DIR__ . '/pages/save-error.md'));
            // Let Grav return the error page
            unset($this->grav['page']);
            $this->grav['page'] = $page;
        }
        
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
            header('Expires: '. $expires_date);
        }
    }

    /**
     * Check that the user is permitted to edit
     *
     * @return boolean
     */
    public function userIsAuthorized($page, $user)
    {
        $result = false;
        if ($page) {
            $config = $this->mergeConfig($page);
            $username = $this->grav['user']->get('username');
            $header = (array)$page->header();
            // Get the groups this user is a member of
            $groups = $this->grav['user']->get('groups');
            // Check whether only certain users or groups may edit this page
            $editable_by = $config->get('editable_by');

            if (isset($editable_by)) {
                //$editable_by = $config->get('editable_by');
                for ($i=0; $i < count($editable_by); $i++) {
                    //dump($editable_by[$i]);
                    if (is_array($editable_by[$i])) { // Array encountered
                        foreach ($editable_by[$i] as $key => $value) {
                            // Examine each element and check for a groups variable
                            if ($key == 'groups') {
                                // Match the groups specified in the editable_by variable
                                // to the groups this user belongs to
                                $match = array_intersect($value, $groups);
                                if (!empty($match)) { // Bingo! There's a match
                                    $result = true;
                                    break; // Break from the foreach loop
                                }
                            }
                            else {
                                if ($key == 'users') {
                                    // Find the logged in user in the list of users
                                    $match = array_search($username, $value);
                                    
                                    if ($match != false) { // Bingo! There's a match
                                        $result = true;
                                        break; // Break from the foreach loop
                                    }
                                }
                            }
                        }
                    }
                    else { // Must be a username
                        if ($editable_by[$i] == $username) { // Bingo ! A username match
                            $result = true;
                            break; // Break from the for loop
                        }
                    }
                }
                return $result;
            }
        }
        else {
            // No specific users or groups are specified in page frontmatter
            // so grant access to any logged in user with proper permissions
            $result = $this->grav['user']->authorize('site.editable') || $this->grav['user']->authorize('admin.super') || $this->grav['user']->authorize('admin.pages');
        }
        return $result;
    }



}
