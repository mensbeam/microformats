<?php
/** @license MIT
 * Copyright 2018 J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam\Microformats;

use Dom\Document;

/** This class simplifies using XPath with both DOMDocument and Dom\HTMLDocument
 * 
 * It only implements the minimum required for Lax's needs, which are modest.
 * It should be suitable for querying both XML and HTML documents, as needed.
 */
class XPath {
    public const NS = [
        'html'     => "http://www.w3.org/1999/xhtml", // XHTML  https://html.spec.whatwg.org/
    ];

    /** @var \DOMXpath|\Dom\XPath */
    protected $x = null;
    protected $isHTMLCompat = false;

    /** @param \DOMDocument|\Dom\Document $doc */
    public function __construct($doc) {
        if ($doc instanceof Document) {
            $this->x = new \Dom\XPath($doc);
        } else {
            $this->x = new \DOMXPath($doc);
        }
        foreach (self::NS as $prefix => $ns) {
            $this->x->registerNamespace($prefix, $ns);
        }
        if ($doc->documentElement->namespaceURI === null) {
            // An HTML document using the traditional DOMDocument class will
            //   normally not have namespaced elements. We note this case here
            //   so that we can later transform queries against the document
            //   to remove the relevant prefixes at time of query.
            if ($doc->documentElement->localName === "html") {
                $this->isHTMLCompat = true;
            }
        }
    }

    /** 
     * @param \DOMNode|\Dom\node|null $contextNode
     * @return \DOMNodeList|\Dom\NodeList|false 
     */
    public function query(string $expression, $contextNode = null) {
        if ($this->isHTMLCompat) {
            $expression = str_replace("html:", "", $expression);
        }
        return $this->x->query($expression, $contextNode);
    }
}
