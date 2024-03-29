<?php

namespace PHPLog;

use PHPLog\Renderer\DefaultRenderer;
use PHPLog\Renderer\Logger as LoggerRenderer;
use PHPLog\Renderer\Throwable;
use PHPLog\Renderer\ArrayRenderer;

/**
 * This class handles determining what renderer to use on a variable.
 * We can add Custom Renderers by calling Logger::addRenderer($class, $renderer)
 * as long as a renderer is an instance of RenderInterface and the class can either be
 * an instance of the variable or the string class name of the variable.
 * This renderer class can even add custom renderers for the following primitive types:
 * 
 * - bool
 * - array
 * - double (float)
 * - int
 * - long
 *
 * we can also handle 'null' objects and apply a renderer for when a variable is null.
 *
 * You set a custom renderer for a primitve type the same way for an object, by either supplying
 * an instance of any of these types or any of the class names above.
 * 
 * This class also handles inheritance based rendering. Say we have 2 renderers, one for 'IndexController'
 * and one for its parent class 'Controller'. And they were added in this order. The string out put will be
 * concatinated in order.
 *
 * @version 1
 * @version 1.1 allowed for inheritance based rendering where we can concatinate output 
 * @version 1.2 updated the renderer to correctly render traversable objects. (it recursively goes through 
 * each of the objects in the collection)
 * from multiple renderers which handle the same object type.
 * @author  Jack Timblin
 */
class Renderer
{
    
    /* the collection of renderers used on primitive types. */
    protected $primatives = array();

    /* the collection of renderers used on objects. */
    protected $renderers = array();

    /* the default renderer to use if we dont have a renderer
	   for a particular object.
    */
    protected $defaultRenderer;

    /**
     * generates a new instance of a renderer with a few default renderers defined.
     * @return Renderer the newly defined renderer.
     */
    public static function newInstance() 
    {
        //set some base renderers.
        //the key is the fully qualified name of the class the renderer is for.
        //the value is the renderer instance to deal with it.
        $map = array(
            '\\PHPLog\\Logger' => array(
                'renderer' => new LoggerRenderer(), 
                'disable'  => true
            ),
            '\\Exception'      => array(
                'renderer' => new Throwable(),       
                'disable'  => false
            )
        );

        return new Renderer($map);

    }

    /**
     * the constructor for a renderer, it cannot be invoked directly
     * a renderer must be initialized using Renderer::newInstance()
     * @param array any default renderers to give to this renderer.
     */
    private function __construct($renderMap) 
    {
        $this->renderers = $renderMap;

        //set class names for primitive types.
        //double and float are both treated as floats.
        $this->primatives = array(
            'float' => null,
            'int' => null,
            'long' => null,
            'array' => array(
                'renderer' => new ArrayRenderer()
            ),
            'bool' => null,
            'null' => null 
        );

        $this->defaultRenderer = new DefaultRenderer();
    }

    /**
     * Attempts to render a variable either using a defined renderer, 
     * the default renderer or just casting to a string.
     * @param mixed $object the object to render.
     * @return string the rendered representation of this $object.
     */
    public function render($object) 
    {

        //check to see if the object is traversable.
        if (is_array($object) || $object instanceof \Traversable) {
            $v = array();
            foreach ($object as $key => $obj) {
                $v[$key] = $this->render($obj);
            }
            if (isset($this->primatives['array'])
                && isset($this->primatives['array']['renderer']) 
                && $this->primatives['array']['renderer'] instanceof RendererInterface) {
                    return $this->primatives['array']['renderer']->render($v, JSON_UNESCAPED_SLASHES);

            }
            //attempt to use the default renderer.
            return $this->applyDefaultRenderScheme($v);

        }

        try {
            //check we dont have any primitive type.
            foreach($this->primatives as $function => $entry) {

                $renderer = $entry['renderer'];
                //if we dont have a renderer, continue.
                if(!($renderer) instanceof RendererInterface) {
                    continue;
                }

                //attempt to check if we have a certain primitive type.
                if(call_user_func('is_'.$function, $object)) {
                    return $renderer->render($object);
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        //lets determine if the object has more than one renderer.
        //inheritance based rendering.
        try {
            $var = null;
            foreach($this->renderers as $class => $entry) {
                if(is_a($object, $class)) {
                    $renderer = $entry['renderer'];
                    if($renderer instanceof RendererInterface) {
                        if(!isset($var)) {
                            $var = '';
                        }
                        $var .= ((strlen($var) > 0) ? ' - ' : '') . $renderer->render($object);
                        if($entry['disable']) {
                            //pointless even checking for more after.
                            $var = $renderer->render($object);
                            break;
                        }
                    }
                }
            }
            if(isset($var)) {
                return $var;
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->applyDefaultRenderScheme($object);

    }

    /**
     * applies the default rendering scheme if no renderer could be
     * used on a certain object.
     * @param mixed $object the object to render into a string.
     * @return string the object in its string representation.
     */
    private function applyDefaultRenderScheme($object) 
    {
        //at this point there was no defined renderer for this object.
        //use the default.
        if($this->defaultRenderer instanceof RendererInterface) {
            try {
                $value = $this->defaultRenderer->render($object);
                return $value;
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }

        //attempt to cast the object to a string.
        $object = ($object !== null) ? $object : '';

        //check to see if it has a method __tostring
        if(is_object($object) && method_exists($object, '__toString')) {
            return (string) $object;
        }

        //check to see if this variable is an array or object.
        if(is_array($object) || is_object($object)) {
            ob_start();
            var_dump($object);
            $object = ob_get_clean();
        }
        $object = (!is_string($object)) ? (string) $object : $object;

        return $object;
    }

    /**
     * sets the default renderer to use
     * @param RenderInterface the default renderer to use.
     */
    public function setDefaultRenderer($default) 
    {
        if($default instanceof RendererInterface) {
            $this->defaultRenderer = $default;
        }
    }

    /**
     * resets the default renderer to the system default renderer.
     */
    public function resetDefaultRenderer() 
    {
        $this->defaultRenderer = new DefaultRenderer();
    }

    /**
     * adds a new renderer to the system, if a renderer already exists for $class
     * then this will be overwritten.
     * @param mixed                                     $class                    either an instance of the object to render or the  fully qualified string name of the class. fully qualified string name of the class.
     * fully qualified string name of the class.
     * @param RendererInterface the renderer to use for $class.
     * @param bool                                      $disableInheritanceRender [optional] whether to allow for appending when there are more than one renderer for a single object type.
     * when there are more than one renderer for a single object type.
     */
    public function addRenderer($class, $renderer, $disableInheritanceRender = false) 
    {
        if(!isset($class) || !($renderer instanceof RendererInterface)) {
            return; //not a valid entry.
        }

        $disableInheritanceRender = (is_bool($disableInheritanceRender)) ? $disableInheritanceRender : false;
        $entry = array('renderer' => $renderer, 'disable' => $disableInheritanceRender);

        //check to see if we have a primitive type.
        if(!is_object($class) && !is_string($class)) {
            foreach(array_keys($this->primatives) as $function) {
                if(call_user_func('is_'.$function, $class)) {
                    $this->primatives[$function] = $entry;
                }
            }
            //return regardless as this primative will just get handled by the default renderer.
            return;
        }

        //check we dont have a string represnetation of the primitive class name.
        if(is_string($class)) {
            if(in_array(strtolower($class), array_keys($this->primatives))) {
                $this->primatives[strtolower($class)] = $entry;
                return; //we have added a renderer.
            }
        }

        $className = (is_string($class)) ? $class : get_class($class);
        $this->renderers[$className] = $entry;

    }

    /**
     * removed a renderer from the system.
     * @param mixed                                     $class  either an instance of the object to render or the  fully qualified string name of the class. fully qualified string name of the class.
     * fully qualified string name of the class.
     * @param RendererInterface the renderer to use for $class.
     */
    public function removeRenderer($class) 
    {
        if(!isset($class)) {
            return;
        }

        //check to see if we have a primitive type.
        if(!is_object($class) && !is_string($class)) {
            foreach(array_keys($this->primatives) as $function) {
                if(call_user_func('is_'.$function, $class)) {
                    unset($this->primatives[$function]);
                }
            }
            //return regardless as this primative will just get handled by the default renderer.
            return;
        }

        //check we dont have a string represnetation of the primitive class name.
        if(is_string($class)) {
            if(in_array(strtolower($class), array_keys($this->primatives))) {
                unset($this->primatives[strtolower($class)]);
                return; //we have added a renderer.
            }
        }

        $className = (is_string($class)) ? $class : get_class($class);
        if(isset($this->renderers[$className])) {
            unset($this->renderers[$className]);
        }
    }

    /**
     * return all of the renderers attached to this renderer instance.
     * @return array the array of renderers.
     */
    public function getRenderers()
    {
        return array_merge($this->primatives, $this->renderers);
    }

}