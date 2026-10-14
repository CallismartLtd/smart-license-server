<?php
/**
 * User agent value object.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Core
 */

namespace SmartLicenseServer\Core;

/**
 * Parses a User-Agent header into a client, an operating system and a device.
 *
 * Philosophy: report what the string claims, normalized; never infer what it
 * does not say. The string is parsed once at construction into an immutable
 * value object, and every getter returns a string (empty when unknown) so the
 * object never throws and callers never null-check.
 *
 * Detection runs in a fixed order, most specific first:
 *
 *  1. Bot      - known crawlers by name, then any `*bot`, `*crawler`, `*spider`, `*scraper` product.
 *  2. Library  - the first product token is a known HTTP library or CLI tool (curl, Guzzle, okhttp...).
 *  3. App      - the first product token is not `Mozilla`/`Opera`: a custom client such as
 *                `SmartLicenseClient/1.2.0 (Linux; x86_64)`.
 *  4. Browser  - browser rules for Mozilla-style strings, with Electron apps and
 *                WebView-embedded apps resolved to the app token they carry.
 *
 * Known limits, inherent to the UA string itself: Windows 11 reports as
 * "Windows NT 10.0", macOS is frozen at 10.15.7 by Safari and Chrome, and
 * iPadOS 13+ Safari presents itself as macOS desktop. These are reported
 * literally; resolving them requires User-Agent Client Hints.
 */
final class UserAgent {

	/*
	|-------------
	| Client types
	|-------------
	*/

	public const TYPE_BROWSER = 'browser';
	public const TYPE_APP     = 'app';
	public const TYPE_LIBRARY = 'library';
	public const TYPE_BOT     = 'bot';
	public const TYPE_UNKNOWN = 'unknown';

	/*
	|-------------
	| Device types
	|-------------
	*/

	public const DEVICE_DESKTOP = 'desktop';
	public const DEVICE_MOBILE  = 'mobile';
	public const DEVICE_TABLET  = 'tablet';
	public const DEVICE_TV      = 'tv';
	public const DEVICE_CONSOLE = 'console';
	public const DEVICE_UNKNOWN = 'unknown';

	/**
	 * Maximum number of bytes inspected; longer strings are truncated.
	 *
	 * @var int
	 */
	public const MAX_LENGTH = 1024;

	/*
	|----------------
	| Detection rules
	|----------------
	*/

	/**
	 * Known crawlers, matched case-insensitively as product names.
	 *
	 * @var string[]
	 */
	private const KNOWN_BOTS = array(
		'Googlebot', 'Google-InspectionTool', 'AdsBot-Google', 'Mediapartners-Google', 'Storebot-Google',
		'bingbot', 'BingPreview', 'YandexBot', 'Baiduspider', 'DuckDuckBot', 'Applebot',
		'facebookexternalhit', 'meta-externalagent', 'Twitterbot', 'LinkedInBot', 'Slackbot',
		'Discordbot', 'TelegramBot', 'WhatsApp', 'Pinterestbot', 'redditbot',
		'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'PetalBot', 'Bytespider', 'SeznamBot',
		'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'Claude-User', 'PerplexityBot',
		'CCBot', 'Amazonbot', 'UptimeRobot', 'Pingdom', 'HeadlessChrome', 'PhantomJS', 'Chrome-Lighthouse',
	);

	/**
	 * Known HTTP libraries and CLI tools, keyed by lowercase product name.
	 *
	 * @var array<string, string>
	 */
	private const KNOWN_LIBRARIES = array(
		'curl'              => 'curl',
		'wget'              => 'Wget',
		'httpie'            => 'HTTPie',
		'postmanruntime'    => 'Postman',
		'insomnia'          => 'Insomnia',
		'guzzlehttp'        => 'Guzzle',
		'guzzle'            => 'Guzzle',
		'wordpress'         => 'WordPress',
		'php'               => 'PHP',
		'python-requests'   => 'Python Requests',
		'python-httpx'      => 'HTTPX',
		'python-urllib'     => 'Python urllib',
		'python'            => 'Python',
		'aiohttp'           => 'aiohttp',
		'okhttp'            => 'OkHttp',
		'axios'             => 'Axios',
		'node-fetch'        => 'node-fetch',
		'node'              => 'Node.js',
		'undici'            => 'undici',
		'deno'              => 'Deno',
		'bun'               => 'Bun',
		'go-http-client'    => 'Go HTTP Client',
		'java'              => 'Java',
		'apache-httpclient' => 'Apache HttpClient',
		'dart'              => 'Dart',
		'dalvik'            => 'Dalvik',
		'cfnetwork'         => 'CFNetwork',
		'applecoremedia'    => 'AppleCoreMedia',
		'libwww-perl'       => 'libwww-perl',
		'ruby'              => 'Ruby',
		'faraday'           => 'Faraday',
		'restsharp'         => 'RestSharp',
		'reqwest'           => 'reqwest',
		'got'               => 'Got',
        'callismart-http'   => 'Callismart HTTP Client',
        'smliser'           => 'Smart License Server'
	);

	/**
	 * Browser rules as [ pattern, name ], most specific first. Group 1 is the version.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private const BROWSERS = array(
		array( '/\bEdg(?:e|A|iOS)?\/([\d.]+)/', 'Edge' ),
		array( '/\bOPR\/([\d.]+)/', 'Opera' ),
		array( '/\bOPiOS\/([\d.]+)/', 'Opera' ),
		array( '/\bOpera Mini\/([\d.]+)/', 'Opera Mini' ),
		array( '/\bOpera\/[\d.]+.*\bVersion\/([\d.]+)/', 'Opera' ),
		array( '/\bOpera[\/ ]([\d.]+)/', 'Opera' ),
		array( '/\bSamsungBrowser\/([\d.]+)/', 'Samsung Internet' ),
		array( '/\bUC ?Browser\/([\d.]+)/i', 'UC Browser' ),
		array( '/\bYaBrowser\/([\d.]+)/', 'Yandex Browser' ),
		array( '/\bVivaldi\/([\d.]+)/', 'Vivaldi' ),
		array( '/\bBrave\/([\d.]+)/', 'Brave' ),
		array( '/\bDuckDuckGo\/([\d.]+)/', 'DuckDuckGo' ),
		array( '/\bMiuiBrowser\/([\d.]+)/', 'MIUI Browser' ),
		array( '/\bHuaweiBrowser\/([\d.]+)/', 'Huawei Browser' ),
		array( '/\bSilk\/([\d.]+)/', 'Amazon Silk' ),
		array( '/\bFBAV\/([\d.]+)/', 'Facebook' ),
		array( '/\bInstagram ([\d.]+)/', 'Instagram' ),
		array( '/\bLine\/([\d.]+)/', 'LINE' ),
		array( '/\bSnapchat\/([\d.]+)/', 'Snapchat' ),
		array( '/\bFxiOS\/([\d.]+)/', 'Firefox' ),
		array( '/\bCriOS\/([\d.]+)/', 'Chrome' ),
		array( '/\bGSA\/([\d.]+)/', 'Google App' ),
		array( '/\bChromium\/([\d.]+)/', 'Chromium' ),
		array( '/\bChrome\/([\d.]+)/', 'Chrome' ),
		array( '/\bFirefox\/([\d.]+)/', 'Firefox' ),
		array( '/\bAndroid\b.*\bVersion\/([\d.]+).*\bSafari\//', 'Android Browser' ),
		array( '/\bVersion\/([\d.]+).*\bSafari\//', 'Safari' ),
		array( '/\bAppleWebKit\b.*\bSafari\/([\d.]+)/', 'Safari' ),
		array( '/\bMSIE ([\d.]+)/', 'Internet Explorer' ),
		array( '/\bTrident\/.*\brv:([\d.]+)/', 'Internet Explorer' ),
	);

	/**
	 * Product tokens that belong to browsers and engines, never to an embedding app.
	 *
	 * @var string[]
	 */
	private const BROWSER_TOKENS = array(
		'mozilla', 'applewebkit', 'gecko', 'chrome', 'chromium', 'safari', 'version', 'mobile',
		'firefox', 'edg', 'edge', 'edga', 'edgios', 'opr', 'opios', 'opera', 'crios', 'fxios', 'gsa',
		'electron', 'samsungbrowser', 'ucbrowser', 'yabrowser', 'vivaldi', 'brave', 'duckduckgo',
		'miuibrowser', 'huaweibrowser', 'silk', 'trident', 'presto', 'headlesschrome', 'kaios',
	);

	/**
	 * Operating system rules as [ pattern, name ], most specific first. Group 1 is the version.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private const OPERATING_SYSTEMS = array(
		array( '/\bWindows Phone(?: OS)? ([\d.]+)/i', 'Windows Phone' ),
		array( '/\bXbox\b/i', 'Xbox' ),
		array( '/\bWindows NT ([\d.]+)/i', 'Windows' ),
		array( '/\bKAIOS(?:\/([\d.]+))?/i', 'KaiOS' ),
		array( '/\bHarmonyOS(?:[ \/]([\d.]+))?/i', 'HarmonyOS' ),
		array( '/\bAndroid(?:[ \/]([\d.]+))?/i', 'Android' ),
		array( '/\biOS[ \/]([\d._]+)/', 'iOS' ),
		array( '/\b(?:iPhone|iPad|iPod)\b(?:[^)]*?\bOS ([\d_]+))?/i', 'iOS' ),
		array( '/\biOS\b/', 'iOS' ),
		array( '/\bCrOS \w+ ([\d.]+)/', 'ChromeOS' ),
		array( '/\bCrOS\b/', 'ChromeOS' ),
		array( '/\bMac OS X(?: ([\d_.]+))?/i', 'macOS' ),
		array( '/\bmacOS(?:[ \/]([\d.]+))?/i', 'macOS' ),
		array( '/\bMacintosh\b/i', 'macOS' ),
		array( '/\bTizen(?:[ \/]([\d.]+))?/i', 'Tizen' ),
		array( '/\b(?:Web0S|webOS)(?:[ \/]([\d.]+))?/i', 'webOS' ),
		array( '/\bPlayStation\b/i', 'PlayStation' ),
		array( '/\bNintendo\b/i', 'Nintendo' ),
		array( '/\b(?:BlackBerry|BB10)\b/i', 'BlackBerry' ),
		array( '/\bUbuntu\b/i', 'Ubuntu' ),
		array( '/\bFedora\b/i', 'Fedora' ),
		array( '/\bDebian\b/i', 'Debian' ),
		array( '/\bFreeBSD\b/i', 'FreeBSD' ),
		array( '/\bOpenBSD\b/i', 'OpenBSD' ),
		array( '/\bNetBSD\b/i', 'NetBSD' ),
		array( '/\bLinux\b/i', 'Linux' ),
		array( '/\bDarwin(?:\/([\d.]+))?/i', 'Darwin' ),
		array( '/\bWindows(?:[ \/](\d[\d.]*))?/i', 'Windows' ),
		array( '/\bWin(?:32|64)\b/i', 'Windows' ),
	);

	/**
	 * Windows NT kernel versions mapped to marketing versions.
	 *
	 * @var array<string, string>
	 */
	private const WINDOWS_VERSIONS = array(
		'10.0' => '10',
		'6.3'  => '8.1',
		'6.2'  => '8',
		'6.1'  => '7',
		'6.0'  => 'Vista',
		'5.2'  => 'XP',
		'5.1'  => 'XP',
		'5.0'  => '2000',
	);

	/**
	 * Operating systems that imply a desktop device for browsers and Electron apps.
	 *
	 * @var string[]
	 */
	private const DESKTOP_SYSTEMS = array(
		'Windows', 'macOS', 'Linux', 'Ubuntu', 'Fedora', 'Debian', 'ChromeOS', 'FreeBSD', 'OpenBSD', 'NetBSD',
	);

	/**
	 * Human-readable labels for device and client types.
	 *
	 * @var array<string, string>
	 */
	private const LABELS = array(
		self::DEVICE_DESKTOP => 'Desktop',
		self::DEVICE_MOBILE  => 'Mobile',
		self::DEVICE_TABLET  => 'Tablet',
		self::DEVICE_TV      => 'TV',
		self::DEVICE_CONSOLE => 'Console',
		self::TYPE_APP       => 'App',
		self::TYPE_LIBRARY   => 'Library',
		self::TYPE_BOT       => 'Bot',
	);

	/*
	|------------
	| Parsed data
	|------------
	*/

	/**
	 * The normalized user agent string.
	 *
	 * @var string
	 */
	private string $user_agent;

	/**
	 * Product tokens outside comments, as [ name, version ] pairs.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private array $products = array();

	/**
	 * Whether the string carries an Electron token.
	 *
	 * @var bool
	 */
	private bool $is_electron = false;

	private string $client_name    = '';
	private string $client_version = '';
	private string $client_type    = self::TYPE_UNKNOWN;
	private string $os_name        = '';
	private string $os_version     = '';
	private string $device_type    = self::DEVICE_UNKNOWN;

	/**
	 * Parse a user agent string.
	 *
	 * @param string $user_agent The raw User-Agent header value.
	 */
	public function __construct( string $user_agent ) {
		$user_agent       = (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $user_agent );
		$user_agent       = (string) preg_replace( '/\s+/', ' ', trim( $user_agent ) );
		$this->user_agent = substr( $user_agent, 0, self::MAX_LENGTH );

		if ( '' === $this->user_agent ) {
			return;
		}

		$this->tokenize();
		$this->detect_client();
		$this->detect_os();
		$this->detect_device();
	}

	/**
	 * Named constructor.
	 *
	 * @param string $user_agent The raw User-Agent header value.
	 * @return self
	 */
	public static function parse( string $user_agent ): self {
		return new self( $user_agent );
	}

	/*
	|--------
	| Getters
	|--------
	*/

	/**
	 * Get the normalized user agent string.
	 *
	 * @return string
	 */
	public function get_user_agent(): string {
		return $this->user_agent;
	}

	/**
	 * Get the client name, e.g. "Chrome", "curl" or "SmartLicenseClient".
	 *
	 * @return string Empty when unknown.
	 */
	public function get_client_name(): string {
		return $this->client_name;
	}

	/**
	 * Get the client version.
	 *
	 * @return string Empty when unknown.
	 */
	public function get_client_version(): string {
		return $this->client_version;
	}

	/**
	 * Get the client name and version, e.g. "Chrome 120.0.6099.71".
	 *
	 * @return string Empty when unknown.
	 */
	public function get_client(): string {
		return self::join( $this->client_name, $this->client_version );
	}

	/**
	 * Get the client type.
	 *
	 * @return string One of the TYPE_* constants.
	 */
	public function get_client_type(): string {
		return $this->client_type;
	}

	/**
	 * Get the operating system name, e.g. "Windows", "Android" or "macOS".
	 *
	 * @return string Empty when unknown.
	 */
	public function get_os_name(): string {
		return $this->os_name;
	}

	/**
	 * Get the operating system version.
	 *
	 * @return string Empty when unknown.
	 */
	public function get_os_version(): string {
		return $this->os_version;
	}

	/**
	 * Get the operating system name and version, e.g. "Android 14".
	 *
	 * @return string Empty when unknown.
	 */
	public function get_os(): string {
		return self::join( $this->os_name, $this->os_version );
	}

	/**
	 * Get the device type.
	 *
	 * @return string One of the DEVICE_* constants.
	 */
	public function get_device_type(): string {
		return $this->device_type;
	}

	/*
	|-----------
	| Predicates
	|-----------
	*/

	/**
	 * Whether the client is a crawler or automation tool.
	 *
	 * @return bool
	 */
	public function is_bot(): bool {
		return self::TYPE_BOT === $this->client_type;
	}

	/**
	 * Whether the client is a web browser.
	 *
	 * @return bool
	 */
	public function is_browser(): bool {
		return self::TYPE_BROWSER === $this->client_type;
	}

	/**
	 * Whether the client is a custom application.
	 *
	 * @return bool
	 */
	public function is_app(): bool {
		return self::TYPE_APP === $this->client_type;
	}

	/**
	 * Whether the client is an HTTP library or CLI tool.
	 *
	 * @return bool
	 */
	public function is_library(): bool {
		return self::TYPE_LIBRARY === $this->client_type;
	}

	/**
	 * Whether the device is a desktop.
	 *
	 * @return bool
	 */
	public function is_desktop(): bool {
		return self::DEVICE_DESKTOP === $this->device_type;
	}

	/**
	 * Whether the device is a mobile phone.
	 *
	 * @return bool
	 */
	public function is_mobile(): bool {
		return self::DEVICE_MOBILE === $this->device_type;
	}

	/**
	 * Whether the device is a tablet.
	 *
	 * @return bool
	 */
	public function is_tablet(): bool {
		return self::DEVICE_TABLET === $this->device_type;
	}

	/*
	|-------
	| Output
	|-------
	*/

	/**
	 * Get a short description, e.g. "Chrome 120.0 on Windows 10 (Desktop)".
	 *
	 * The bracketed label is the device type when known, otherwise the client
	 * type for non-browsers, e.g. "curl 8.4.0 (Library)". Empty parts are omitted.
	 *
	 * @return string
	 */
	public function get_description(): string {
		$description = '' !== $this->get_client() ? $this->get_client() : 'Unknown';

		if ( '' !== $this->get_os() ) {
			$description .= ' on ' . $this->get_os();
		}

		$label = self::LABELS[ $this->device_type ] ?? self::LABELS[ $this->client_type ] ?? '';

		if ( '' !== $label ) {
			$description .= ' (' . $label . ')';
		}

		return $description;
	}

	/**
	 * Get all parsed data.
	 *
	 * @return array{user_agent: string, client_name: string, client_version: string, client_type: string, os_name: string, os_version: string, device_type: string, description: string}
	 */
	public function to_array(): array {
		return array(
			'user_agent'     => $this->user_agent,
			'client_name'    => $this->client_name,
			'client_version' => $this->client_version,
			'client_type'    => $this->client_type,
			'os_name'        => $this->os_name,
			'os_version'     => $this->os_version,
			'device_type'    => $this->device_type,
			'description'    => $this->get_description(),
		);
	}

	/**
	 * Get the short description.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->get_description();
	}

	/*
	|----------
	| Tokenizer
	|----------
	*/

	/**
	 * Split the string into product tokens, skipping parenthesized comments.
	 */
	private function tokenize(): void {
		preg_match_all( '/\([^()]*\)|([^\s()\/;,\[\]]+)(?:\/([^\s()\/;,\[\]]+))?/', $this->user_agent, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			if ( empty( $match[1] ) ) {
				continue;
			}

			$this->products[] = array( $match[1], $match[2] ?? '' );
		}

		$this->is_electron = (bool) preg_match( '/\bElectron\/[\d.]+/', $this->user_agent );
	}

	/*
	|----------------
	| Client detection
	|----------------
	*/

	/**
	 * Resolve client name, version and type.
	 */
	private function detect_client(): void {
		if ( $this->detect_bot() ) {
			return;
		}

		$first = $this->products[0] ?? array( '', '' );
		$key   = strtolower( $first[0] );

		if ( isset( self::KNOWN_LIBRARIES[ $key ] ) ) {
			$this->set_client( self::KNOWN_LIBRARIES[ $key ], $first[1], self::TYPE_LIBRARY );
			return;
		}

		if ( '' !== $key && 'mozilla' !== $key && 'opera' !== $key ) {
			$this->set_client( $first[0], $first[1], self::TYPE_APP );
			return;
		}

		$this->detect_browser();
	}

	/**
	 * Detect known and generic crawlers.
	 *
	 * @return bool Whether a bot was found.
	 */
	private function detect_bot(): bool {
		$names = implode( '|', array_map( static fn( string $name ): string => preg_quote( $name, '/' ), self::KNOWN_BOTS ) );

		if ( preg_match( '/(?<![\w-])(' . $names . ')(?:\/([\w.\-]+))?/i', $this->user_agent, $match ) ) {
			foreach ( self::KNOWN_BOTS as $name ) {
				if ( 0 === strcasecmp( $name, $match[1] ) ) {
					$this->set_client( $name, $match[2] ?? '', self::TYPE_BOT );
					return true;
				}
			}
		}

		if ( preg_match( '/(?<![\w-])((?!cubot)[a-z][\w.\-]*(?:bot|crawler|spider|scraper))(?![a-z])(?:\/([\w.\-]+))?/i', $this->user_agent, $match ) ) {
			$this->set_client( $match[1], $match[2] ?? '', self::TYPE_BOT );
			return true;
		}

		return false;
	}

	/**
	 * Detect browsers, Electron apps and WebView-embedded apps in Mozilla-style strings.
	 */
	private function detect_browser(): void {
		$app = $this->find_app_token();

		if ( $this->is_electron ) {
			if ( null !== $app ) {
				$this->set_client( $app[0], $app[1], self::TYPE_APP );
			} else {
				preg_match( '/\bElectron\/([\d.]+)/', $this->user_agent, $match );
				$this->set_client( 'Electron', $match[1] ?? '', self::TYPE_APP );
			}
			return;
		}

		foreach ( self::BROWSERS as [ $pattern, $name ] ) {
			if ( ! preg_match( $pattern, $this->user_agent, $match ) ) {
				continue;
			}

			if ( 'Chrome' === $name && $this->is_android_webview() ) {
				if ( null !== $app ) {
					$this->set_client( $app[0], $app[1], self::TYPE_APP );
				} else {
					$this->set_client( 'Android WebView', $match[1], self::TYPE_BROWSER );
				}
				return;
			}

			$this->set_client( $name, $match[1] ?? '', self::TYPE_BROWSER );
			return;
		}

		if ( null !== $app ) {
			$this->set_client( $app[0], $app[1], self::TYPE_APP );
			return;
		}

		if ( preg_match( '/\b(?:iPhone|iPad|iPod)\b/', $this->user_agent ) && str_contains( $this->user_agent, 'AppleWebKit' ) ) {
			$this->set_client( 'iOS WebView', '', self::TYPE_BROWSER );
		}
	}

	/**
	 * Find the first versioned product token that is not a browser or engine token.
	 *
	 * @return array{0: string, 1: string}|null
	 */
	private function find_app_token(): ?array {
		foreach ( $this->products as $product ) {
			if ( '' === $product[1] || ! preg_match( '/^[A-Za-z][\w.\-]*$/', $product[0] ) ) {
				continue;
			}

			if ( in_array( strtolower( $product[0] ), self::BROWSER_TOKENS, true ) ) {
				continue;
			}

			return $product;
		}

		return null;
	}

	/**
	 * Whether the string is an Android WebView ("; wv)" or the legacy "Version/4.0 Chrome/").
	 *
	 * @return bool
	 */
	private function is_android_webview(): bool {
		return str_contains( $this->user_agent, 'Android' )
			&& (bool) preg_match( '/;\s*wv\)|\bVersion\/[\d.]+ Chrome\//', $this->user_agent );
	}

	/**
	 * Store the client.
	 *
	 * @param string $name    Client name.
	 * @param string $version Client version.
	 * @param string $type    One of the TYPE_* constants.
	 */
	private function set_client( string $name, string $version, string $type ): void {
		$this->client_name    = $name;
		$this->client_version = self::normalize_version( $version );
		$this->client_type    = $type;
	}

	/*
	|------------
	| OS detection
	|------------
	*/

	/**
	 * Resolve operating system name and version.
	 */
	private function detect_os(): void {
		foreach ( self::OPERATING_SYSTEMS as [ $pattern, $name ] ) {
			if ( ! preg_match( $pattern, $this->user_agent, $match ) ) {
				continue;
			}

			$version = self::normalize_version( $match[1] ?? '' );

			if ( 'Windows' === $name && isset( self::WINDOWS_VERSIONS[ $version ] ) && str_contains( $match[0], 'NT' ) ) {
				$version = self::WINDOWS_VERSIONS[ $version ];
			}

			$this->os_name    = $name;
			$this->os_version = $version;
			return;
		}
	}

	/*
	|----------------
	| Device detection
	|----------------
	*/

	/**
	 * Resolve the device type from explicit tokens only.
	 */
	private function detect_device(): void {
		$ua = $this->user_agent;

		if ( self::TYPE_BOT === $this->client_type ) {
			return;
		}

		if ( preg_match( '/\b(?:SmartTV|SMART-TV|Smart TV|HbbTV|GoogleTV|Google TV|AppleTV|Apple TV|Android ?TV|CrKey|BRAVIA|Roku|NetCast|AFT[A-Z]+)\b|\bTizen\b[^)]*\bTV\b|\bWeb0S\b/i', $ua ) ) {
			$this->device_type = self::DEVICE_TV;
			return;
		}

		if ( preg_match( '/\b(?:PlayStation|Xbox|Nintendo)\b/i', $ua ) ) {
			$this->device_type = self::DEVICE_CONSOLE;
			return;
		}

		if ( preg_match( '/\b(?:iPad|Tablet|Kindle|PlayBook|Nexus (?:7|9|10)|SM-T\d+|GT-P\d+|KF[A-Z]{2,})\b|\bSilk\//i', $ua ) ) {
			$this->device_type = self::DEVICE_TABLET;
			return;
		}

		if ( preg_match( '/\b(?:iPhone|iPod|Windows Phone|BlackBerry|BB10|Opera Mini|KAIOS|Phone)\b|Mobi/i', $ua ) ) {
			$this->device_type = self::DEVICE_MOBILE;
			return;
		}

		$renders_ui = self::TYPE_BROWSER === $this->client_type || ( self::TYPE_APP === $this->client_type && $this->is_electron );

		// Android browsers omit "Mobile" on tablets by convention.
		if ( $renders_ui && 'Android' === $this->os_name ) {
			$this->device_type = self::DEVICE_TABLET;
			return;
		}

		if ( $renders_ui && in_array( $this->os_name, self::DESKTOP_SYSTEMS, true ) ) {
			$this->device_type = self::DEVICE_DESKTOP;
		}
	}

	/*
	|--------
	| Helpers
	|--------
	*/

	/**
	 * Normalize a version string: underscores to dots, trimmed of stray separators.
	 *
	 * @param string $version Raw version.
	 * @return string
	 */
	private static function normalize_version( string $version ): string {
		return trim( str_replace( '_', '.', $version ), '.-' );
	}

	/**
	 * Join a name and version with a space, omitting empty parts.
	 *
	 * @param string $name    Name.
	 * @param string $version Version.
	 * @return string
	 */
	private static function join( string $name, string $version ): string {
		return trim( $name . ' ' . $version );
	}
}