<?php

namespace PHPLog;

/**
 * The interface that all renderers in the system have to implement in order
 * to be used as a valid renderer.
 * @version 1
 * @author Jack Timblin
 */
interface RendererInterface
{

    /**
     * attempts to render the object passed to it into its string representation.
     * @param mixed $object the object to render.
     * @param int   $options options to pass to the renderer.
     * @return string the string representation of $object.
     */
    public function render($object, $options = 0);
}