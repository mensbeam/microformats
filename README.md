# Microformats

A generic [Microformats](https://microformats.io/) parser for PHP. While it similar to [php-mf2](https://github.com/microformats/php-mf2), it combines a more accurate HTML parser with more consistent performance characteristics, and passes tests which the other library does not pass.

## Usage

Functionality is provided for parsing from a file, from a string, and from an HTML element (a `\DOMElement` object), as well as for serializing to JSON.

The parsing methods all return a Microformats structure as an array. The [Microformats wiki](https://microformats.org/wiki/microformats2) includes some sample structures in JSON format.

### Parsing from a file
