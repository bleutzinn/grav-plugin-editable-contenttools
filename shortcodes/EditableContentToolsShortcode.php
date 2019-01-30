<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Uri;
use Grav\Plugin\EditableContentToolsPlugin;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;


/**
 * Inserts content from the editable content page and makes it editable for the editor.
 */
class EditableContentToolsShortcode extends Shortcode
{
    public function init()
    {
        $this->shortcode->getHandlers()->add('editable', function(ShortcodeInterface $sc) {
            $sc_name = $sc->getParameter('name', '');
            /*
            if (preg_match('/example-[0-9]/', $sc_name, $matches)) {
                return EditableContentToolsPlugin::showShortcodeExamples($matches[0]);
            }
            else {
            */
                // Get variables to be used in the template
                $page = $this->grav['page'];
                $route = $page->route();
                $content = $sc->getContent();
                // Render the template
                $output = $this->twig->processTemplate('editable-region.html.twig', [
                    'name' => $sc_name,
                    'route' => $route,
                    'content' => $content,
                ]);
                // Return the rendered output (HTML)
                return $output;
            /*
            }
            */
        });
    }
}
