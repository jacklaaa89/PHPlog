<?php

namespace PHPLog\Renderer;

use PHPLog\RendererInterface;
use PHPLog\Logger as LoggerInstance;

/**
 * This renderer is used to convert a Logger instance into a string.
 * If the logger has a valid __toString method, then this is used, otherwise var_dump is used.
 * @version 1
 * @author Jack Timblin
 */
class Logger implements RendererInterface
{

    /**
     * @see PHPLog\RendererInterface::render()
     */
    public function render($object, $options = 0) 
    {

        if($object instanceof LoggerInstance) {
            if(method_exists($object, '__toString')) {
                return (string) $object;
            }
            ob_clean();
            var_dump($object);
            return ob_get_clean();
        }

        return '';

    }

}