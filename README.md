# Microformats

A generic [Microformats](https://microformats.io/) parser for PHP. While it similar to [php-mf2](https://github.com/microformats/php-mf2), it combines a more accurate HTML parser with more consistent performance characteristics, and it is believed to have fewer bugs.

## Usage

Functionality is provided for parsing from an HTTP URL, from a file, from a string, and from an HTML element (a `\DOMElement` or `\Dom\HTMLElement` object), as well as for serializing to JSON. A static method of the `MensBeam\Microformats` class is provided for each task.

The parsing methods all return a Microformats structure as an array. The [Microformats wiki](https://microformats.org/wiki/microformats2) includes some sample structures in JSON format.


### Parsing from a URL

```php
\MensBeam\Microformats::fromUrl(string $url, array $options = []): ?array
```

The `$url` argument is an HTTP(S) URL to an HTML resource; redirections will be followed if neceesary. If the resource cannot be fetched `null` will be returned.

The `$options` argument is a list of options for the Microformats parser. See below for details.

### Parsing from a file

```php
\MensBeam\Microformats::fromFile(string $file, string $contentType, string $url, array $options = []): ?array
```

The `$file` argument is the path to a local file. If the file cannot be opened for reading `null` will be returned.

The `$contentType` argument is a string containing the value of the file's HTTP `Content-Type` header, if known. This may be used to provide the HTML parser with character encoding information.

The `$url` argument is a string containing the file's effective URL. This is used to resolve any relative URLs in the input.

The `$options` argument is a list of options for the Microformats parser. See below for details.

### Parsing from a string

```php
\MensBeam\Microformats::fromString(string $input, string $contentType, string $url, array $options = []): array
```

The `$input` argument is the string to parse for micrformats.

The `$contentType` argument is a string containing the value of the string's HTTP `Content-Type` header, if known. This may be used to provide the HTML parser with character encoding information.

The `$url` argument is a string containing the string's effective URL. This is used to resolve any relative URLs in the input.

The `$options` argument is a list of options for the Microformats parser. See below for details.

### Parsing from an HTML element

```php
\MensBeam\Microformats::fromHtmlElement(\DOMElement|\Dom\HTMLElement $input, string $url, array $options = []): array
```

The `$input` argument is the element to parse for micrformats. Typically this would be the `documentElement`, but any element may be parsed.

The `$url` argument is a string containing the string's effective URL. This is used to resolve any relative URLs in the input.

The `$options` argument is a list of options for the Microformats parser. See below for details.

### Serializing to JSON

```php
\MensBeam\Microformats::toJson(array $data, int $flags = 0, int $depth = 512): string
```

Since Microformats data is represented as a structure of nested arrays, some of which are associative ("objects" in JSON parlance) and may be empty, it is necessary to convert such empty array into PHP `stdClass` objects before they are serialized to JSON. This method performs these conversions before passing the result to [the `json_encode` function](https://www.php.net/manual/en/function.json-encode). Its parameters are the same as that of `json_encode`.

## Options

The parsing methods all optionally take an `$options` array as an argument. These options are all flags, either for experimental features, or for backwards-compatible features no longer used by default. The options are as followings:

| Key                 | Type    | Default | Description
|---------------------|---------|---------|------------
| `dateNormalization` | Boolean | `true`  | This optiona enables date and time normalization throughout microformat parsing rather than only where required by the specification
| `impliedTz`         | Boolean | `false` | Time values in microformats may have an implied date associated with them taken from a prior date value in the same microformat structure. This option allows for a time zone to be implied as well, if a time does not include its time zone.
| `lang`              | Boolean | `true`  | This option determines whether language information is retrieved from the parsed document and included in the output, in `lang` keys. Both Microformat structures and embedded markup (`e-` property) structures are affected by this options.
| `thoroughTrim`      | Boolean | `true`  | This option uses the more thorough whitespace-trimming algorithm proposed for future standardization rather than the "classic", simpler whitespace-trimming algorithm mandated by the parsing specification. This affects both `p-` and `e-` properties.

## Change log

### Version 0.2.0 (2026-02-17)

- Accept and use `Dom\HTMLDocument` when available (since PHP 8.4)

### Version 0.1.0 (2023-06-28)

- Initial release
