<?php
/**
 * Extension to MIME type map (2026 Updated).
 *
 * Used primarily for setting the 'Content-Type' HTTP header when serving a file
 * based on its known file extension.
 *
 * NOTE: This map is NOT suitable for file upload security. For secure file
 * validation, always use PHP's finfo extension (binary signature detection).
 *
 * Updated for 2026: Added modern formats (AVIF, WebP video, HEIC sequences),
 * removed legacy variants, consolidated duplicates, and improved organization.
 *
 * @package SmartLicenseServer\FileSystem\bundles
 * @author Callistus Nwachukwu
 * @updated 2026
 */

return [
    // --- Images (Modern & Legacy) ---
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'jpe'   => 'image/jpeg',
    'png'   => 'image/png',
    'gif'   => 'image/gif',
    'webp'  => 'image/webp',
    'avif'  => 'image/avif',
    'bmp'   => 'image/bmp',
    'ico'   => 'image/x-icon',
    'svg'   => 'image/svg+xml',
    'svgz'  => 'image/svg+xml',
    'tiff'  => 'image/tiff',
    'tif'   => 'image/tiff',
    'heic'  => 'image/heic',
    'heics' => 'image/heic-sequence',
    'heif'  => 'image/heif',
    'heifs' => 'image/heif-sequence',

    // --- Documents & Text ---
    'pdf'   => 'application/pdf',
    'txt'   => 'text/plain',
    'log'   => 'text/plain',
    'csv'   => 'text/csv',
    'tsv'   => 'text/tab-separated-values',
    'json'  => 'application/json',
    'json5' => 'application/json',
    'xml'   => 'application/xml',
    'html'  => 'text/html',
    'htm'   => 'text/html',
    'css'   => 'text/css',
    'md'    => 'text/markdown',
    'yaml'  => 'application/x-yaml',
    'yml'   => 'application/x-yaml',
    'rtf'   => 'application/rtf',
    'ini'   => 'text/plain',
    'conf'  => 'text/plain',

    // --- Microsoft Office Documents ---
    'doc'   => 'application/msword',
    'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'docm'  => 'application/vnd.ms-word.document.macroEnabled.12',
    'dotx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
    'dotm'  => 'application/vnd.ms-word.template.macroEnabled.12',
    'xls'   => 'application/vnd.ms-excel',
    'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xlsm'  => 'application/vnd.ms-excel.sheet.macroEnabled.12',
    'xlsb'  => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
    'xltx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
    'xltm'  => 'application/vnd.ms-excel.template.macroEnabled.12',
    'xlam'  => 'application/vnd.ms-excel.addin.macroEnabled.12',
    'ppt'   => 'application/vnd.ms-powerpoint',
    'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'pptm'  => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
    'ppsx'  => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
    'ppsm'  => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
    'potx'  => 'application/vnd.openxmlformats-officedocument.presentationml.template',
    'potm'  => 'application/vnd.ms-powerpoint.template.macroEnabled.12',
    'ppam'  => 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
    'sldx'  => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
    'sldm'  => 'application/vnd.ms-powerpoint.slide.macroEnabled.12',
    'mdb'   => 'application/vnd.ms-access',
    'mpp'   => 'application/vnd.ms-project',

    // --- OpenDocument Formats (ODF) ---
    'odt'   => 'application/vnd.oasis.opendocument.text',
    'ods'   => 'application/vnd.oasis.opendocument.spreadsheet',
    'odp'   => 'application/vnd.oasis.opendocument.presentation',
    'odg'   => 'application/vnd.oasis.opendocument.graphics',
    'odf'   => 'application/vnd.oasis.opendocument.formula',
    'odb'   => 'application/vnd.oasis.opendocument.database',

    // --- Archives ---
    'zip'   => 'application/zip',
    'rar'   => 'application/vnd.rar',
    '7z'    => 'application/x-7z-compressed',
    'tar'   => 'application/x-tar',
    'gz'    => 'application/gzip',
    'tgz'   => 'application/gzip',
    'bz2'   => 'application/x-bzip2',
    'dmg'   => 'application/x-apple-diskimage',
    'iso'   => 'application/x-iso9660-image',

    // --- Audio ---
    'mp3'   => 'audio/mpeg',
    'wav'   => 'audio/wav',
    'ogg'   => 'audio/ogg',
    'oga'   => 'audio/ogg',
    'flac'  => 'audio/flac',
    'aac'   => 'audio/aac',
    'm4a'   => 'audio/mp4',
    'webm'  => 'audio/webm',

    // --- Video ---
    'mp4'   => 'video/mp4',
    'm4v'   => 'video/mp4',
    'webm'  => 'video/webm',
    'mov'   => 'video/quicktime',
    'avi'   => 'video/x-msvideo',
    'wmv'   => 'video/x-ms-wmv',
    'mpeg'  => 'video/mpeg',
    'mpg'   => 'video/mpeg',
    'ogv'   => 'video/ogg',
    '3gp'   => 'video/3gpp',
    'flv'   => 'video/x-flv',
    'mkv'   => 'video/x-matroska',
    'mks'   => 'video/x-matroska',

    // --- Fonts ---
    'ttf'   => 'font/ttf',
    'otf'   => 'font/otf',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'eot'   => 'application/vnd.ms-fontobject',

    // --- Programming & Scripts ---
    'html'  => 'text/html',
    'xhtml' => 'application/xhtml+xml',
    'js'    => 'application/javascript',
    'ts'    => 'application/typescript',
    'jsx'   => 'text/jsx',
    'tsx'   => 'text/tsx',
    'vue'   => 'text/x-vue',
    'php'   => 'application/x-httpd-php',
    'py'    => 'application/x-python',
    'rb'    => 'application/x-ruby',
    'go'    => 'text/x-go',
    'rs'    => 'text/x-rust',
    'java'  => 'text/x-java-source',
    'cpp'   => 'text/x-c++src',
    'c'     => 'text/x-c',
    'h'     => 'text/x-chdr',
    'sh'    => 'application/x-sh',
    'bash'  => 'application/x-sh',
    'pl'    => 'application/x-perl',
    'sql'   => 'application/x-sql',
    'bat'   => 'application/x-msdos-program',

    // --- Executables & System Files ---
    'exe'   => 'application/octet-stream',
    'dll'   => 'application/x-msdownload',
    'bin'   => 'application/octet-stream',
    'apk'   => 'application/vnd.android.package-archive',
    'aab'   => 'application/vnd.android.package-archive',
    'ipa'   => 'application/octet-stream',
    'deb'   => 'application/x-deb',
    'rpm'   => 'application/x-rpm',
    'msi'   => 'application/x-msi',

    // --- Web & Platform Specific ---
    'webmanifest' => 'application/manifest+json',
    'wasm'  => 'application/wasm',
    'xpi'   => 'application/x-xpinstall',
    'crx'   => 'application/x-chrome-extension',
    'pkg'   => 'application/vnd.apple.installer+xml',

    // --- Certificates & Keys ---
    'crt'   => 'application/x-x509-ca-cert',
    'cer'   => 'application/pkix-cert',
    'pem'   => 'application/x-pem-file',
    'key'   => 'application/x-openssl-key',
];