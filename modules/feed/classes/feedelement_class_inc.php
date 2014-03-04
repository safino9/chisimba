<?php

class feedelement implements ArrayAccess
{

    /**
     * @var DOMElement
     */
    protected $_element;

    /**
     * @var feedelement
     */
    protected $_parentElement;

    /**
     * @var boolean
     */
    protected $_appended = true;


    /**
     * feedelement constructor.
     *
     * @param DOMElement $element The DOM element we're encapsulating.
     */
    public function __construct($element = null)
    {
        $this->_element = $element;
    }


    /**
     * Get a DOM representation of the element
     *
     * Returns the underlying DOM object, which can then be
     * manipulated with full DOM methods.
     *
     * @return DOMDocument
     */
    public function getDOM()
    {
        return $this->_element;
    }


    /**
     * Update the object from a DOM element
     *
     * Take a DOMElement object, which may be originally from a call
     * to getDOM() or may be custom created, and use it as the
     * DOM tree for this feedelement.
     *
     * @param DOMElement $element
     */
    public function setDOM(DOMElement $element)
    {
        $this->_element = $this->_element->ownerDocument->importNode($element, true);
    }


    /**
     * Set the parent element of this object to another
     * feedelement.
     *
     * @internal
     */
    public function setParent(feedelement $element)
    {
        $this->_parentElement = $element;
        $this->_appended = false;
    }


    /**
     * Appends this element to its parent if necessary.
     *
     * @internal
     */
    protected function ensureAppended()
    {
        if (!$this->_appended) {
            $this->_parentElement->getDOM()->appendChild($this->_element);
            $this->_appended = true;
            $this->_parentElement->ensureAppended();
        }
    }


    /**
     * Get an XML string representation of this element
     *
     * Returns a string of this element's XML, including the XML
     * prologue.
     *
     * @return string
     */
    public function saveXML()
    {
        // Return a complete document including XML prologue.
        $doc = new DOMDocument($this->_element->ownerDocument->version,
                               $this->_element->ownerDocument->actualEncoding);
        $doc->appendChild($doc->importNode($this->_element, true));
        return $doc->saveXML();
    }


    /**
     * Get the XML for only this element
     *
     * Returns a string of this element's XML without prologue.
     *
     * @return string
     */
    public function saveXMLFragment()
    {
        return $this->_element->ownerDocument->saveXML($this->_element);
    }


    /**
     * Map variable access onto the underlying entry representation.
     *
     * Get-style access returns a feedelement representing the
     * child element accessed. To get string values, use method syntax
     * with the __call() overriding.
     *
     * @param string $var The property to access.
     * @return mixed
     */
    public function __get($var)
    {
        $nodes = $this->_children($var);
        $length = count($nodes);

        if ($length == 1) {
            return new feedelement($nodes[0]);
        } elseif ($length > 1) {
            return array_map(create_function('$e', 'return new feedelement($e);'), $nodes);
        } else {
            // When creating anonymous nodes for __set chaining, don't
            // call appendChild() on them. Instead we pass the current
            // element to them as an extra reference; the child is
            // then responsible for appending itself when it is
            // actually set. This way "if ($foo->bar)" doesn't create
            // a phantom "bar" element in our tree.
            if (strpos($var, ':') !== false) {
                list($ns, $elt) = explode(':', $var, 2);
                $node = $this->_element->ownerDocument->createElementNS(feed::lookupNamespace($ns), $elt);
            } else {
                $node = $this->_element->ownerDocument->createElement($var);
            }
            $node = new feedelement($node);
            $node->setParent($this);
            return $node;
        }
    }


    /**
     * Map variable sets onto the underlying entry representation.
     *
     * @param string $var The property to change.
     * @param string $val The property's new value.
     */
    public function __set($var, $val)
    {
        $this->ensureAppended();

        $nodes = $this->_children($var);
        if (!$nodes) {
            if (strpos($var, ':') !== false) {
                list($ns, $elt) = explode(':', $var, 2);
                $node = $this->_element->ownerDocument->createElementNS(feed::lookupNamespace($ns), $var, $val);
                $this->_element->appendChild($node);
            } else {
                $node = $this->_element->ownerDocument->createElement($var, $val);
                $this->_element->appendChild($node);
            }
        } elseif (count($nodes) > 1) {
            throw new customException('Cannot set the value of multiple tags simultaneously.');
        } else {
            $nodes[0]->nodeValue = $val;
        }
    }


    /**
     * Map isset calls onto the underlying entry representation.
     *
     * Only supported by PHP 5.1 and later.
     */
    public function __isset($var)
    {
        // Look for access of the form {ns:var}. We don't use
        // _children() here because we can break out of the loop
        // immediately once we find something.
        if (strpos($var, ':') !== false) {
            list($ns, $elt) = explode(':', $var, 2);
            foreach ($this->_element->childNodes as $child) {
                if ($child->localName == $elt && $child->prefix == $ns) {
                    return true;
                }
            }
        } else {
            foreach ($this->_element->childNodes as $child) {
                if ($child->localName == $var) {
                    return true;
                }
            }
        }
    }


    /**
     * Get the value of an element with method syntax.
     *
     * Map method calls to get the string value of the requested
     * element. If there are multiple elements that match, this will
     * return an array of those objects.
     *
     * @param string $var The element to get the string value of.
     *
     * @return mixed The node's value, null, or an array of nodes.
     */
    public function __call($var, $unused)
    {
        $nodes = $this->_children($var);

        if (!$nodes) {
            return null;
        } elseif (count($nodes) > 1) {
            return $nodes;
        } else {
            return $nodes[0]->nodeValue;
        }
    }


    /**
     * Remove all children matching $var.
     *
     * Only supported by PHP 5.1 and later.
     */
    public function __unset($var)
    {
        $nodes = $this->_children($var);
        foreach ($nodes as $node) {
            $parent = $node->parentNode;
            $parent->removeChild($node);
        }
    }


    /**
     * Returns the nodeValue of this element when this object is used
     * in a string context.
     *
     * @internal
     */
    public function __toString()
    {
        return $this->_element->nodeValue;
    }


    /**
     * Finds children with tagnames matching $var
     *
     * Similar to SimpleXML's children() method.
     *
     * @param string Tagname to match, can be either namespace:tagName or just tagName.
     * @return array
     */
    protected function _children($var)
    {
        $found = array();

        // Look for access of the form {ns:var}.
        if (strpos($var, ':') !== false) {
            list($ns, $elt) = explode(':', $var, 2);
            foreach ($this->_element->childNodes as $child) {
                if ($child->localName == $elt && $child->prefix == $ns) {
                    $found[] = $child;
                }
            }
        } else {
            foreach ($this->_element->childNodes as $child) {
                if ($child->localName == $var) {
                    $found[] = $child;
                }
            }
        }

        return $found;
    }


    /**
     * Required by the ArrayAccess interface.
     *
     * @internal
     */
    public function offsetExists($offset)
    {
        if (strpos($offset, ':') !== false) {
            list($ns, $attr) = explode(':', $offset, 2);
            return $this->_element->hasAttributeNS(Zend_Feed::lookupNamespace($ns), $attr);
        } else {
            return $this->_element->hasAttribute($offset);
        }
    }


    /**
     * Required by the ArrayAccess interface.
     *
     * @internal
     */
    public function offsetGet($offset)
    {
        if (strpos($offset, ':') !== false) {
            list($ns, $attr) = explode(':', $offset, 2);
            return $this->_element->getAttributeNS(Zend_Feed::lookupNamespace($ns), $attr);
        } else {
            return $this->_element->getAttribute($offset);
        }
    }


    /**
     * Required by the ArrayAccess interface.
     *
     * @internal
     */
    public function offsetSet($offset, $value)
    {
        $this->ensureAppended();

        if (strpos($offset, ':') !== false) {
            list($ns, $attr) = explode(':', $offset, 2);
            return $this->_element->setAttributeNS(feed::lookupNamespace($ns), $attr, $value);
        } else {
            return $this->_element->setAttribute($offset, $value);
        }
    }


    /**
     * Required by the ArrayAccess interface.
     *
     * @internal
     */
    public function offsetUnset($offset)
    {
        if (strpos($offset, ':') !== false) {
            list($ns, $attr) = explode(':', $offset, 2);
            return $this->_element->removeAttributeNS(feed::lookupNamespace($ns), $attr);
        } else {
            return $this->_element->removeAttribute($offset);
        }
    }

}
?>