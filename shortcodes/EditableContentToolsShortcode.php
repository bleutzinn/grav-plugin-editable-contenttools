<?php

namespace Grav\Plugin\Shortcodes;

use Thunder\Shortcode\Shortcode\ShortcodeInterface;


/**
 * Wraps content of an editable shortcode to turn it
 * into an editable region for ContentTools.
 */
class EditableContentToolsShortcode extends Shortcode
{
    public function init()
    {
        $this->shortcode->getHandlers()->add('editable', function(ShortcodeInterface $sc) {
            
            // Get variables to be used in the template
            $sc_name = $sc->getParameter('name', '');
            $content = $sc->getContent();
            $route = $this->grav['page']->route();

            // Render the template
            $output = $this->twig->processTemplate('editable-region.html.twig', [
                'name' => $sc_name,
                'content' => $content,
                'route' => $route,
            ]);

            // Return the rendered output (HTML)
            return $output;
        });
    }
}
