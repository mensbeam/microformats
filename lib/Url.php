<?php
/** @license MIT
 * Copyright 2018 J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Microformats;

use Psr\Http\Message\UriInterface;

/** Normalized URI representation, compatible with the PSR-7 URI interface
 *
 * The following features are implemented:
 *
 * - The full PSR-7 `UriInterface` interface
 * - Correct handling of both URLs and URNs
 * - Relative URL resolution
 * - Encoding normalization
 * - Scheme normalization
 * - IDNA normalization
 * - IPv6 address normalization
 *
 * Some things this class does not do:
 *
 * - Handle non-standard schemes (e.g. ed2k)
 * - Collapse paths
 *
 * This class should not be used with XML namespace URIs,
 * as the normalizations performed will change the values
 * of some namespaces.
 */
class Url implements UriInterface {
    protected const URI_PATTERN = <<<'PCRE'
<^
(?:
    (?:
        ([a-z][a-z0-9\.\-\+]*): |       # absolute URI
        :?(?=[\\/]{2})                  # scheme-relative URI
    )
    ([\\/]{1,2}[^/\?\#\\]*)?            # authority part
)?
([^\?\#]*)                              # path part
(\?[^\#]*)?                             # query part
(\#.*)?                                 # fragment part
$>six
PCRE;
        protected const STRICT_URI_PATTERN = <<<'PCRE'
<^
(?:
    (?:
        ([a-z][a-z0-9\.\-\+]*): |       # absolute URI
        :?(?=//)                        # scheme-relative URI
    )
    (//?[^/\?\#]*)?                     # authority part
)?
([^\?\#]*)                              # path part
(\?[^\#]*)?                             # query part
(\#.*)?                                 # fragment part
$>six
PCRE;
    protected const HOST_PATTERN = '/^(\[[a-f0-9:\.]*\]|[^:]*)(:[^\/]*)?$/si';
    protected const USER_PATTERN = '/^([^:]*)(?::(.*))?$/s';
    protected const SCHEME_PATTERN = '/^(?:[a-z][a-z0-9\.\-\+]*|)$/i';
    protected const IPV4_PATTERN = '/^[\.xX0-9a-fA-F\x{ff10}-\x{ff19}\x{ff21}-\x{ff26}\x{ff41}-\x{ff46}\x{ff38}\x{ff58}\x{ff0e}]*$/u'; // matches ASCII and fullwidth equivalents
    protected const IPV6_PATTERN = '/^\[[^\]]+\]$/i';
    protected const PORT_PATTERN = '/^\d*$/';
    protected const FORBIDDEN_HOST_PATTERN = '/[\x{00}\t\n\r #%\/:<>\?@\[\]\\\^]/';
    protected const FORBIDDEN_OPAQUE_HOST_PATTERN = '/[\x{00}\t\n\r #\/:<>\?@\[\]\\\^]/'; // forbidden host excluding %
    protected const WINDOWS_AUTHORITY_PATTERN = '/^[\/\\\\]{1,2}[a-zA-Z][:|]$/';
    protected const WINDOWS_PATH_PATTERN = '/(?:^|\/)([a-zA-Z])[:|]($|[\/#\?].*)/';
    protected const WHITESPACE_CHARS = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x20";
    protected const FULLWIDTH_CHARS = ["\u{FF10}" => "0", "\u{FF11}" => "1", "\u{FF12}" => "2", "\u{FF13}" => "3", "\u{FF14}" => "4", "\u{FF15}" => "5", "\u{FF16}" => "6", "\u{FF17}" => "7", "\u{FF18}" => "8", "\u{FF19}" => "9", "\u{FF21}" => "A", "\u{FF22}" => "B", "\u{FF23}" => "C", "\u{FF24}" => "D", "\u{FF25}" => "E", "\u{FF26}" => "F", "\u{FF41}" => "a", "\u{FF42}" => "b", "\u{FF43}" => "c", "\u{FF44}" => "d", "\u{FF45}" => "e", "\u{FF46}" => "f", "\u{FF38}" => "X", "\u{FF58}" => "x", "\u{FF0E}" => "."];
    protected const PERCENT_ENCODE_SETS = [
        'C0'       => "",
        'fragment' => " \"<>`",
        'path'     => " \"<>`?#{}",
        'userinfo' => " \"<>`?#{}/:;=@[\]^|",
        'query'    => " \"<>#", // single-quote as well if scheme is special
    ];
    protected const SPECIAL_SCHEMES = [
        'ftp'   => 21,
        'file'  => null,
        'http'  => 80,
        'https' => 443,
        'ws'    => 80,
        'wss'   => 443,
    ];

    protected $scheme = "";
    protected $user = "";
    protected $pass = "";
    protected $host = null;
    protected $port = null;
    protected $path = "";
    protected $query = null;
    protected $fragment = null;
    protected $specialScheme = false;

    public static function fromUri(UriInterface $uri): self {
        return ($uri instanceof self) ? $uri : new self((string) $uri);
    }

    public static function fromString(string $url, string $baseUrl = null): ?self {
        try {
            return new static($url, $baseUrl);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    public function __construct(string $url, string $baseUrl = null) {
        $url = str_replace(["\t", "\n", "\r"], "", trim($url, self::WHITESPACE_CHARS));
        $base = null;
        $pattern = self::URI_PATTERN;
        reprocess:
        if (preg_match($pattern, $url, $match)) {
            [$url, $scheme, $authority, $path, $query, $fragment] = array_pad($match, 6, "");
            // if the URI is not unambigously a URL, parse the base URI
            if (!$base && $baseUrl && (!$scheme || substr($authority, 0, 2) !== "//")) {
                $base = new static($baseUrl);
            }
            // set the scheme; use the base scheme if necessary
            $this->setScheme($scheme ?: ($base->scheme ?? ""));
            // if the scheme is non-special, re-process with a stricter pattern
            if (!$this->specialScheme && $pattern !== self::STRICT_URI_PATTERN) {
                $pattern = self::STRICT_URI_PATTERN;
                goto reprocess;
            }
            // make various checks to see if the authority should actually be the starts of the path
            if ($authority && !in_array($authority[1] ?? "", ["/", "\\"])) {
                // the URI is something like x:/example.com/
                if ($base && $this->scheme === $base->scheme && !$base->isUrn()) {
                    // URI is a relative URL; add authority to path instead
                    $path = $authority.$path;
                    $authority = "";
                } elseif ($this->scheme === "file") {
                    // URI is an absolute file: URL; add authority to path and set the authority to the default authority
                    $path = $authority.$path;
                    $authority = "//";
                } elseif ($this->specialScheme) {
                    // URI is an absolute URL with a typo; add a slash to the authority
                    $authority = "/$authority";
                } else {
                    // URI is a URN; add authority to path instead
                    $path = $authority.$path;
                    $authority = "";
                }
            } elseif ($scheme && !$authority) {
                // the URI is something like x:example.com/
                if ($base && $this->scheme === $base->scheme && !$base->isUrn()) {
                    // URI is a relative URL; continue processing
                } elseif ($this->scheme === "file") {
                    // URI is an absolute file: URL; add the authority delimiter and default authority to the URL and reprocess
                    $url = preg_replace("/:/", ":///", $url, 1);
                    goto reprocess;
                } elseif ($this->specialScheme) {
                    // URI is an absolute URL; add the authority delimiter to the URL and reprocess
                    $url = preg_replace("/:/", "://", $url, 1);
                    goto reprocess;
                } else {
                    // URI is a URN; continue processing
                }
            } elseif ($this->scheme === "file" && preg_match(self::WINDOWS_AUTHORITY_PATTERN, $authority)) {
                // URI is something like file://C:/path
                $path = $authority.$path;
                $authority = "//";
            }
            if ($authority) {
                $auth = substr($authority, 2);
                if (($cleft = strrpos($auth, "@")) !== false) {
                    if (preg_match(self::USER_PATTERN, substr($auth, 0, $cleft), $match)) {
                        $this->setUser($match[1]);
                        $this->setPass($match[2] ?? "");
                    }
                    if (preg_match(self::HOST_PATTERN, substr($auth, $cleft + 1), $match)) {
                        $this->setHost($match[1]);
                        if ($match[2] ?? "") {
                            $this->setPort(substr($match[2], 1));
                        }
                    }
                    if (!$this->specialScheme && !strlen((string) $this->host)) {
                        throw new \InvalidArgumentException("Credentials with default host in URL");
                    }
                } elseif (preg_match(self::HOST_PATTERN, $auth, $match)) {
                    $this->setHost($match[1]);
                    if ($match[2] ?? "") {
                        if (!$this->specialScheme && !strlen($match[1])) {
                            throw new \InvalidArgumentException("Port with default host in URL");
                        }
                        $this->setPort(substr($match[2], 1));
                    }
                }
            }
            // resolve with the base, if necessary
            if ($base) {
                // if the base is a URN without a path-like path, this is invalid
                if (!$scheme && !$authority && (strlen($path) || $query) && $base->isUrn() && ($base->path[0] ?? "") !== "/") {
                    throw new \InvalidArgumentException("Base URI cannot be a URN");
                }
                if (!$authority && $this->scheme === $base->scheme) {
                    if ($base->host !== null) {
                        $this->host = $base->host;
                        $this->port = $base->port;
                        $this->user = $base->user;
                        $this->pass = $base->pass;
                    }
                    $this->setPath($path, $base->path);
                    if (!strlen($path)) {
                        if (!$query) {
                            $this->query = $base->query;
                        }
                    }
                } else {
                    $this->setPath($path);
                }
            } else {
                $this->setPath($path);
            }
            if ($query) {
                $this->setQuery(substr($query, 1));
            }
            if ($fragment) {
                $this->setFragment(substr($fragment, 1));
            }
        } else {
            throw new \InvalidArgumentException("String is not a valid URI");
        }
    }

    public function isUrn(): bool {
        return $this->scheme && $this->host === null && !$this->specialScheme;
    }

    public function getAuthority() {
        $host = $this->getHost();
        if (strlen($host) > 0) {
            $userInfo = $this->getUserInfo();
            $port = $this->getPort();
            return (strlen($userInfo) ? $userInfo."@" : "").$host.(!is_null($port) ? ":".$port : "");
        }
        return "";
    }

    public function getFragment() {
        return $this->fragment ?? "";
    }

    public function getHost() {
        return $this->host ?? "";
    }

    public function getPath() {
        return $this->path ?? "";
    }

    public function getPort() {
        return $this->port;
    }

    public function getQuery() {
        return $this->query ?? "";
    }

    public function getScheme() {
        return $this->scheme ?? "";
    }

    public function getUserInfo() {
        if (strlen($this->user ?? "")) {
            return $this->user.(strlen($this->pass ?? "") ? ":".$this->pass : "");
        }
        return "";
    }

    public function withFragment($fragment) {
        $out = clone $this;
        if (!strlen((string) $fragment)) {
            $out->fragment = null;
        } else {
            $out->setFragment((string) $fragment);
        }
        return $out;
    }

    public function withHost($host) {
        if ($host === "") {
            $host = null;
        }
        $out = clone $this;
        $out->setHost($host);
        return $out;
    }

    public function withPath($path) {
        $out = clone $this;
        $out->setPath((string) $path);
        return $out;
    }

    public function withPort($port) {
        $out = clone $this;
        $out->setPort((string) $port);
        return $out;
    }

    public function withQuery($query) {
        $out = clone $this;
        if (!strlen((string) $query)) {
            $out->query = null;
        } else {
            $out->setQuery((string) $query);
        }
        return $out;
    }

    public function withScheme($scheme) {
        $out = clone $this;
        $out->setScheme((string) $scheme);
        return $out;
    }

    public function withUserInfo($user, $password = null) {
        $out = clone $this;
        $out->setUser((string) $user);
        $out->setPass((string) $password);
        return $out;
    }

    public function __toString() {
        return $this->serializeScheme().$this->serializeAuthority().$this->serializePath().$this->serializeQuery().$this->serializeFragment();
    }

    protected function serializeScheme(): string {
        return $this->scheme ? $this->scheme.":" : "";
    }

    protected function serializeAuthority(): string {
        if ($this->host !== null) {
            $auth = $this->host;
            $auth .= (strlen($auth) && !is_null($this->port)) ? ":".$this->port : "";
            $user = $this->user.(strlen($this->pass) ? ":".$this->pass : "");
            $auth = (strlen($auth) && strlen($user)) ? "$user@$auth" : $auth;
            return "//$auth";
        }
        return "";
    }

    protected function serializePath(): string {
        if ($this->host !== null) {
            $out = "";
            if ((strlen($this->path) && $this->path[0] !== "/") || (!strlen($this->path) && $this->specialScheme)) {
                $out .= "/";
            }
            $out .= $this->specialScheme ? preg_replace("<^/{2,}/>", "/", $this->path) : $this->path;
            return $out;
        }
        return $this->path;
    }

    protected function serializeQuery(): string {
        return is_string($this->query) ? "?".$this->query : "";
    }

    protected function serializeFragment(): string {
        return is_string($this->fragment) ? "#".$this->fragment : "";
    }

    protected function setScheme(string $value): void {
        if (preg_match(self::SCHEME_PATTERN, $value)) {
            $this->scheme = strtolower($value);
            $this->specialScheme = array_key_exists($this->scheme, self::SPECIAL_SCHEMES);
        } else {
            throw new \InvalidArgumentException("Invalid scheme specified");
        }
    }

    protected function setUser(string $value): void {
        $this->user = $this->percentEncode($value, "userinfo");
    }

    protected function setPass(string $value): void {
        $this->pass = $this->percentEncode($value, "userinfo");
    }
    
    protected function setHost(?string $value): void {
        if ($this->scheme === "file" && strtolower($value) === "localhost") {
            $this->host = "";
        } else {
            $this->host = $this->parseHost($value);
        }
        
    }

    protected function setPort(string $value): void {
        if (!strlen($value)) {
            $this->port = null;
        } elseif ($this->scheme === "file") {
            throw new \InvalidArgumentException("Port in file: scheme must always be null");
        } elseif (preg_match(self::PORT_PATTERN, (string) $value) && (int) $value <= 0xFFFF) {
            $value = (int) $value;
            if ($this->specialScheme && $value === self::SPECIAL_SCHEMES[$this->scheme]) {
                $this->port = null;
            } else {
                $this->port = $value;
            }
        } else {
            throw new \InvalidArgumentException("Port must be an integer between 0 and 65535, or null");
        }
    }

    protected function setPath(string $value, string $base = ""): void {
        if ($this->specialScheme) {
            $value = str_replace("\\", "/", $value);
        }
        $value = $this->collapsePath($value, $base);
        $this->path = $this->percentEncode($value, $this->isUrn() ? "C0" : "path");
    }

    protected function setQuery(?string $value): void {
        if (is_null($value)) {
            $this->query = $value;
        } else {
            $this->query = $this->percentEncode($value, "query");
        }
    }

    protected function setFragment(?string $value): void {
        if (is_null($value)) {
            $this->fragment = $value;
        } else {
            $this->fragment = $this->percentEncode($value, "fragment");
        }
    }

    protected function collapsePath(string $path, string $base = ""): string {
        $winDrive = "";
        if ($path === "") {
            return $base;
        } elseif ($this->scheme === "file") {
            if (preg_match(self::WINDOWS_PATH_PATTERN, $path, $match)) {
                // If a Windows drive letter is present, the host is implicitly localhost
                $this->setHost("");
                $path = "/".$match[1].":".$match[2];
                $winDrive = $match[1].":";
            } elseif (preg_match(self::WINDOWS_PATH_PATTERN, $base, $match)) {
                $this->setHost("");
                $winDrive = $match[1].":";
            }
        } elseif ($path === "/") {
            return $path;
        }
        $abs = $path[0] === "/";
        $dir = $path[-1] === "/";
        $term = $dir || preg_match("</(?:\.|%2E){1,2}$>i", $path);
        $path = explode("/", (string) substr($path, (int) $abs, strlen($path) - ($abs + $dir)));
        if (!$abs && strlen($base)) {
            // also consider the base path, if appropriate
            $abs = $base[0] === "/";
            $base = explode("/", substr($base, (int) $abs));
            array_pop($base);
            $path = array_merge($base, $path);
        }
        $out = [];
        foreach ($path as $s) {
            if ($s === "" && !$out && $this->scheme === "file") {
                // empty segments before the first non-empty segment in a file: URL should be skipped
                continue;
            } elseif (preg_match('/^(?:\.|%2E)$/i', $s)) {
                // current-directory segment; these should simply be omitted
                continue;
            } elseif (preg_match('/^(?:\.|%2E){2}$/i', $s)) {
                // parent-directory segment; pop a directory off the output
                array_pop($out);
            } else {
                $out[] = $s;
            }
        }
        if ($winDrive && ($out[0] ?? "") !== $winDrive) {
            if (!$out) {
                $term = true;
            }
            array_unshift($out, $winDrive);
        } elseif (!$out) {
            return $abs ? "/" : "";
        }
        return ($abs ? "/" : "").implode("/", $out).($term ? "/" : "");
    }

    protected function percentEncode(string $data, string $type): string {
        assert(array_key_exists($type, self::PERCENT_ENCODE_SETS), "Invalid percent-encoding set");
        $out = "";
        $end = strlen($data);
        for ($p = 0; $p < $end; $p++) {
            $c = $data[$p];
            $o = ord($c);
            if ($o > 0x1F && $o < 0x7F && !strspn($c, self::PERCENT_ENCODE_SETS[$type]) && !($this->specialScheme && $type === "query" && $c === "'")) {
                $out .= $c;
            } else {
                $out .= strtoupper("%".str_pad(dechex($o), 2, "0", \STR_PAD_LEFT));
            }
        }
        return $out;
    }

    protected function parseHost(?string $host): ?string {
        if (strlen($host ?? "")) {
            if ($host[0] === "[") {
                if ($host[-1] !== "]") {
                    throw new \InvalidArgumentException("Invalid host in URL");
                }
                // normalize IPv6 addresses
                $addr = $this->parseIPv6(substr($host, 1, strlen($host) - 2));
                if ($addr !== null) {
                    return "[".$addr."]";
                } else {
                    throw new \InvalidArgumentException("Invalid host in URL");
                }
            } elseif (!$this->specialScheme) {
                // simply apply percent-encoding where necessary to hosts for non-special schemes
                if (preg_match(self::FORBIDDEN_OPAQUE_HOST_PATTERN, $host)) {
                    throw new \InvalidArgumentException("Invalid host in URL");
                }
                return $this->percentEncode($host, "C0");
            }
            $host = rawurldecode($host);
            $domain = null;
            if (preg_match(self::IPV4_PATTERN, $host)) {
                $domain = $this->parseIPv4($host);
            }
            if ($domain === null && function_exists("idn_to_ascii") && function_exists("idn_to_utf8")) {
                $domain = [];
                foreach (explode(".", $host) as $label) {
                    if (!strlen($label)) {
                        $domain[] = $label;
                    } else {
                        $label = idn_to_ascii($label, \IDNA_NONTRANSITIONAL_TO_ASCII | \IDNA_CHECK_BIDI, \INTL_IDNA_VARIANT_UTS46);
                        if ($label === false || idn_to_utf8($label, \IDNA_NONTRANSITIONAL_TO_UNICODE | \IDNA_USE_STD3_RULES, \INTL_IDNA_VARIANT_UTS46) === false) {
                            $domain = false;
                            break;
                        }
                        $domain[] = $label;
                    }
                }
                $domain = is_array($domain) ? implode(".", $domain) : $domain;
            }
            $domain = $domain ?? strtolower($host);
            if ($domain === false || preg_match(self::FORBIDDEN_HOST_PATTERN, $domain)) {
                throw new \InvalidArgumentException("Invalid host in URL");
            }
            return $domain;
        } elseif ($this->specialScheme && $this->scheme !== "file") {
            throw new \InvalidArgumentException("Invalid host in URL");
        }
        return $host;
    }

    protected function parseIPv4(string $input) {
        // first parse the address; this is a literal implementation of https://url.spec.whatwg.org/#concept-ipv4-parser
        assert(strlen($input));
        $input = str_replace(array_keys(self::FULLWIDTH_CHARS), self::FULLWIDTH_CHARS, $input);
        $input = explode(".", $input);
        if ($input[sizeof($input) - 1] === "" && sizeof($input) > 1) {
            array_pop($input);
        }
        if (sizeof($input) > 4) {
            return null;
        }
        $numbers = [];
        foreach ($input as $p) {
            if ($p === "") {
                return null;
            }
            $result = $this->parseIPv4Number($p);
            if (!is_int($result)) {
                return null;
            } else {
                $numbers[] = $result;
            }
        }
        $ipv4 = array_pop($numbers);
        $counter = 0;
        if ($ipv4 >= 256 ** (5 - (sizeof($numbers) + 1))) {
            return false;
        }
        foreach ($numbers as $n) {
            if ($n > 255) {
                return false;
            }
            $ipv4 += $n * 256 ** (3 - $counter);
            $counter++;
        }
        // now re-serialize the address
        $out = [];
        for ($a = 0; $a < 4; $a++) {
            $out[] = $ipv4 % 256;
            $ipv4 = floor($ipv4 / 256);
        }
        return implode(".", array_reverse($out));        
    }

    protected function parseIPv4Number(string $n): ?int {
        if ($n === "") {
            return 0;
        } elseif (preg_match("/^0x/i", $n)) {
            $n = substr($n, 2);
            $r = 16;
        } elseif ($n[0] === "0") {
            $n = substr($n, 1);
            $r = 8;
        } else {
            $r = 10;
        }
        if (
            ($r === 10 && preg_match("/[^0-9]/", $n))
            || ($r === 8 && preg_match("/[^0-7]/", $n))
            || ($r === 16 && preg_match("/[^0-9a-fA-F]/", $n))
        ) {
            return null;
        }
        return (int) base_convert($n, $r, 10);
    }

    protected function parseIPv6(string $input): ?string {
        // first parse the address; this is a literal implementation of https://url.spec.whatwg.org/#concept-ipv6-parser
        $addr = array_fill(0, 8, 0);
        $pieceIndex = 0;
        $compress = null;
        $p = 0;
        $end = strlen($input);
        if ($end && $input[$p] === ":") {
            if (($input[$p + 1] ?? "") !== ":") {
                return null;
            }
            $p += 2;
            $compress = ++$pieceIndex;
        }
        while ($p < $end) {
            $c = $input[$p];
            if ($pieceIndex > 7) {
                return null;
            }
            if ($c === ":") {
                if (!is_null($compress)) {
                    return null;
                }
                $p++;
                $compress = ++$pieceIndex;
                continue;
            }
            $value = $length = 0;
            while ($length < 4 && strspn($c, "0123456789ABCDEFabcdef")) {
                $value = $value * 0x10 + hexdec($c);
                $c = $input[++$p] ?? "";
                $length++;
            }
            if ($c === ".") {
                if (!$length || $pieceIndex > 6) {
                    return null;
                }
                $p -= $length;
                $numbersSeen = 0;
                while ($p < $end) {
                    $ipv4Piece = null;
                    if ($numbersSeen > 0) {
                        if ($c === "." && $numbersSeen < 4) {
                            $p++;
                        } else {
                            return null;
                        }
                    }
                    if (!is_numeric($input[$p] ?? "")) {
                        return null;
                    }
                    while (strspn($c = ($input[$p] ?? ""), "0123456789")) {
                        if (is_null($ipv4Piece)) {
                            $ipv4Piece = (int) $c;
                        } elseif ($ipv4Piece === 0) {
                            return null;
                        } else {
                            $ipv4Piece = $ipv4Piece * 10 + (int) $c;
                        }
                        if ($ipv4Piece > 255) {
                            return null;
                        }
                        $p++;
                    }
                    $addr[$pieceIndex] = $addr[$pieceIndex] * 0x100 + $ipv4Piece;
                    $numbersSeen++;
                    if ($numbersSeen === 2 || $numbersSeen === 4) {
                        $pieceIndex++;
                    }
                }
                if ($numbersSeen !== 4) {
                    return null;
                }
                break;
            } elseif ($c === ":") {
                $p++;
                if ($p >= $end) {
                    return null;
                }
            } elseif ($p < $end) {
                return null;
            }
            $addr[$pieceIndex++] = $value;
        }
        if (!is_null($compress)) {
            $swaps = $pieceIndex - $compress;
            $pieceIndex = 7;
            while ($pieceIndex !== 0 && $swaps > 0) {
                $dst = $compress + $swaps - 1;
                $cur = $addr[$dst];
                $addr[$dst] = $addr[$pieceIndex];
                $addr[$pieceIndex] = $cur;
                $pieceIndex--;
                $swaps--;
            }
        } elseif (is_null($compress) && $pieceIndex !== 8) {
            return null;
        }
        // now serialize the address back; this in turn is a literal implementation of https://url.spec.whatwg.org/#concept-ipv6-serializer
        $out = "";
        // find the longest compressible span
        $compress = ['index' => null, 'span' => 0];
        $candidate = null;
        $span = 0;
        for ($a = 0; $a <= sizeof($addr); $a++) {
            if (!($addr[$a] ?? 0x10000)) {
                if (is_null($candidate)) {
                    $candidate = $a;
                }
                $span++;
            } elseif (!is_null($candidate)) {
                if ($span > $compress['span']) {
                    $compress['index'] = $candidate;
                    $compress['span'] = $span;
                }
                $candidate = null;
                $span = 0;
            }
        }
        $compress = $compress['span'] > 1 ? $compress['index'] : null;
        $ignoreZero = false;
        for ($a = 0; $a < 8; $a++) {
            if ($ignoreZero && $addr[$a] === 0) {
                continue;
            } elseif ($ignoreZero) {
                $ignoreZero = false;
            }
            if ($a === $compress) {
                $out .= !$a ? "::" : ":";
                $ignoreZero = true;
                continue;
            }
            $out .= dechex($addr[$a]);
            $out .= $a !== 7 ? ":" : "";
        }
        return $out;
    }
}
