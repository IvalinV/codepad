<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App Version Name
    |--------------------------------------------------------------------------
    |
    | This is the human-readable version of your app (e.g. "1.0.0"). It is
    | used as the versionName in Android builds and may be displayed in
    | the app or console to determine the current app release version.
    |
    */

    'version' => env('NATIVEPHP_APP_VERSION', 'DEBUG'),

    /*
    |--------------------------------------------------------------------------
    | App Version Code
    |--------------------------------------------------------------------------
    |
    | This is the internal numeric version code used for Play Store builds.
    | It must increase with every release. This is used as versionCode in
    | Android builds and is required for publishing updates to the store.
    |
    */

    'version_code' => env('NATIVEPHP_APP_VERSION_CODE', 1),

    /*
    |--------------------------------------------------------------------------
    | App ID
    |--------------------------------------------------------------------------
    |
    | This is the unique ID of your application used by Android to identify
    | the app package. It is typically written in reverse domain format,
    | such as "com.nativephp.app".
    |
    */

    'app_id' => env('NATIVEPHP_APP_ID'),

    /*
    |--------------------------------------------------------------------------
    | Deeplink Scheme
    |--------------------------------------------------------------------------
    |
    | The deep link scheme to use for opening your app from URLs. For
    | example, using the scheme "nativephp" allows links like:
    | nativephp://some/path to open the app directly.
    |
    */

    'deeplink_scheme' => env('NATIVEPHP_DEEPLINK_SCHEME'),

    /*
    |--------------------------------------------------------------------------
    | Deeplink Host
    |--------------------------------------------------------------------------
    |
    | The domain name to associate with verified HTTPS links and NFC tags.
    | This allows URLs like https://your-host.com/path to open your app
    | when tapped from an NFC tag or clicked from the browser.
    |
    */

    'deeplink_host' => env('NATIVEPHP_DEEPLINK_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Start URL
    |--------------------------------------------------------------------------
    |
    | The initial URL/path to load when the app starts. This should be a
    | path relative to the app root (e.g., "/dashboard", "/onboarding").
    | If not set, the app will load the root path ("/").
    |
    */

    'start_url' => env('NATIVEPHP_START_URL', '/'),

    /*
    |--------------------------------------------------------------------------
    | Development Team (iOS)
    |--------------------------------------------------------------------------
    |
    | The Apple Developer Team ID to use for code signing iOS apps. This is
    | automatically detected from your installed certificates, but you can
    | override it here if needed. Find your Team ID in your Apple Developer
    | account under Membership details.
    |
    */
    'development_team' => env('NATIVEPHP_DEVELOPMENT_TEAM'),

    /*
    |--------------------------------------------------------------------------
    | Environment Keys to Clean Up
    |--------------------------------------------------------------------------
    |
    | These are keys that will be removed from the .env file during app
    | bundling to prevent secrets or development credentials from being
    | leaked. Wildcards are supported (e.g. AWS_* or *_SECRET).
    |
    */

    'cleanup_env_keys' => [
        'AWS_*',
        'GITHUB_*',
        'DO_SPACES_*',
        '*_SECRET',
        'DB_PASSWORD',
        'DB_USERNAME',
    ],

    /*
    |--------------------------------------------------------------------------
    | Files to Exclude Before Bundling
    |--------------------------------------------------------------------------
    |
    | These files and folders will be removed before the final bundle is
    | built for production. You may use glob/wildcard patterns here to
    | skip unnecessary assets like logs, sessions, or temp data.
    |
    */

    'cleanup_exclude_files' => [
        'storage/framework/sessions',
        'storage/framework/cache',
        'storage/framework/testing',
        'storage/logs/laravel.log',

        // Phiki grammars — only 16 are used by App\Enums\Language, plus the
        // transitive closure of grammars they `include` (e.g. php pulls in
        // html/js/css/sql; ruby pulls in haml/markdown/shell/etc. for heredocs).
        // csharp.json and ruby.json are excluded from this list on purpose:
        // those two are patched in resources/grammars (see AppServiceProvider
        // PATCHED_GRAMMARS) and the vendor originals below are dead.
        'vendor/phiki/phiki/resources/grammars/actionscript-3.json',
        'vendor/phiki/phiki/resources/grammars/ada.json',
        'vendor/phiki/phiki/resources/grammars/angular-expression.json',
        'vendor/phiki/phiki/resources/grammars/angular-html.json',
        'vendor/phiki/phiki/resources/grammars/angular-inline-style.json',
        'vendor/phiki/phiki/resources/grammars/angular-inline-template.json',
        'vendor/phiki/phiki/resources/grammars/angular-let-declaration.json',
        'vendor/phiki/phiki/resources/grammars/angular-template.json',
        'vendor/phiki/phiki/resources/grammars/angular-template-blocks.json',
        'vendor/phiki/phiki/resources/grammars/angular-ts.json',
        'vendor/phiki/phiki/resources/grammars/antlers.json',
        'vendor/phiki/phiki/resources/grammars/apache.json',
        'vendor/phiki/phiki/resources/grammars/apex.json',
        'vendor/phiki/phiki/resources/grammars/apl.json',
        'vendor/phiki/phiki/resources/grammars/applescript.json',
        'vendor/phiki/phiki/resources/grammars/ara.json',
        'vendor/phiki/phiki/resources/grammars/asciidoc.json',
        'vendor/phiki/phiki/resources/grammars/asm.json',
        'vendor/phiki/phiki/resources/grammars/astro.json',
        'vendor/phiki/phiki/resources/grammars/awk.json',
        'vendor/phiki/phiki/resources/grammars/ballerina.json',
        'vendor/phiki/phiki/resources/grammars/beancount.json',
        'vendor/phiki/phiki/resources/grammars/berry.json',
        'vendor/phiki/phiki/resources/grammars/bicep.json',
        'vendor/phiki/phiki/resources/grammars/blade.json',
        'vendor/phiki/phiki/resources/grammars/bsl.json',
        'vendor/phiki/phiki/resources/grammars/cadence.json',
        'vendor/phiki/phiki/resources/grammars/cairo.json',
        'vendor/phiki/phiki/resources/grammars/clarity.json',
        'vendor/phiki/phiki/resources/grammars/cobol.json',
        'vendor/phiki/phiki/resources/grammars/codeowners.json',
        'vendor/phiki/phiki/resources/grammars/codeql.json',
        'vendor/phiki/phiki/resources/grammars/common-lisp.json',
        'vendor/phiki/phiki/resources/grammars/coq.json',
        'vendor/phiki/phiki/resources/grammars/cpp-macro.json',
        'vendor/phiki/phiki/resources/grammars/crystal.json',
        'vendor/phiki/phiki/resources/grammars/csv.json',
        'vendor/phiki/phiki/resources/grammars/cue.json',
        'vendor/phiki/phiki/resources/grammars/cypher.json',
        'vendor/phiki/phiki/resources/grammars/d.json',
        'vendor/phiki/phiki/resources/grammars/dax.json',
        'vendor/phiki/phiki/resources/grammars/desktop.json',
        'vendor/phiki/phiki/resources/grammars/djot.json',
        'vendor/phiki/phiki/resources/grammars/dotenv.json',
        'vendor/phiki/phiki/resources/grammars/dream-maker.json',
        'vendor/phiki/phiki/resources/grammars/edge.json',
        'vendor/phiki/phiki/resources/grammars/elm.json',
        'vendor/phiki/phiki/resources/grammars/emacs-lisp.json',
        'vendor/phiki/phiki/resources/grammars/erb.json',
        'vendor/phiki/phiki/resources/grammars/es-tag-css.json',
        'vendor/phiki/phiki/resources/grammars/es-tag-glsl.json',
        'vendor/phiki/phiki/resources/grammars/es-tag-html.json',
        'vendor/phiki/phiki/resources/grammars/es-tag-sql.json',
        'vendor/phiki/phiki/resources/grammars/es-tag-xml.json',
        'vendor/phiki/phiki/resources/grammars/fennel.json',
        'vendor/phiki/phiki/resources/grammars/fish.json',
        'vendor/phiki/phiki/resources/grammars/fluent.json',
        'vendor/phiki/phiki/resources/grammars/fortran-fixed-form.json',
        'vendor/phiki/phiki/resources/grammars/fortran-free-form.json',
        'vendor/phiki/phiki/resources/grammars/gdresource.json',
        'vendor/phiki/phiki/resources/grammars/gdscript.json',
        'vendor/phiki/phiki/resources/grammars/gdshader.json',
        'vendor/phiki/phiki/resources/grammars/genie.json',
        'vendor/phiki/phiki/resources/grammars/gherkin.json',
        'vendor/phiki/phiki/resources/grammars/gleam.json',
        'vendor/phiki/phiki/resources/grammars/glimmer-js.json',
        'vendor/phiki/phiki/resources/grammars/glimmer-ts.json',
        'vendor/phiki/phiki/resources/grammars/hack.json',
        'vendor/phiki/phiki/resources/grammars/haxe.json',
        'vendor/phiki/phiki/resources/grammars/hcl.json',
        'vendor/phiki/phiki/resources/grammars/hjson.json',
        'vendor/phiki/phiki/resources/grammars/hlsl.json',
        'vendor/phiki/phiki/resources/grammars/http.json',
        'vendor/phiki/phiki/resources/grammars/hurl.json',
        'vendor/phiki/phiki/resources/grammars/hxml.json',
        'vendor/phiki/phiki/resources/grammars/hy.json',
        'vendor/phiki/phiki/resources/grammars/imba.json',
        'vendor/phiki/phiki/resources/grammars/jinja.json',
        'vendor/phiki/phiki/resources/grammars/jinja-html.json',
        'vendor/phiki/phiki/resources/grammars/jison.json',
        'vendor/phiki/phiki/resources/grammars/json5.json',
        'vendor/phiki/phiki/resources/grammars/jsonnet.json',
        'vendor/phiki/phiki/resources/grammars/jssm.json',
        'vendor/phiki/phiki/resources/grammars/kdl.json',
        'vendor/phiki/phiki/resources/grammars/kotlin.json',
        'vendor/phiki/phiki/resources/grammars/kusto.json',
        'vendor/phiki/phiki/resources/grammars/lean.json',
        'vendor/phiki/phiki/resources/grammars/liquid.json',
        'vendor/phiki/phiki/resources/grammars/llvm.json',
        'vendor/phiki/phiki/resources/grammars/logo.json',
        'vendor/phiki/phiki/resources/grammars/luau.json',
        'vendor/phiki/phiki/resources/grammars/maml.json',
        'vendor/phiki/phiki/resources/grammars/markdown-vue.json',
        'vendor/phiki/phiki/resources/grammars/marko.json',
        'vendor/phiki/phiki/resources/grammars/matlab.json',
        'vendor/phiki/phiki/resources/grammars/mdc.json',
        'vendor/phiki/phiki/resources/grammars/mdx.json',
        'vendor/phiki/phiki/resources/grammars/mermaid.json',
        'vendor/phiki/phiki/resources/grammars/mipsasm.json',
        'vendor/phiki/phiki/resources/grammars/mojo.json',
        'vendor/phiki/phiki/resources/grammars/move.json',
        'vendor/phiki/phiki/resources/grammars/narrat.json',
        'vendor/phiki/phiki/resources/grammars/neon.json',
        'vendor/phiki/phiki/resources/grammars/nextflow.json',
        'vendor/phiki/phiki/resources/grammars/nginx.json',
        'vendor/phiki/phiki/resources/grammars/nim.json',
        'vendor/phiki/phiki/resources/grammars/nix.json',
        'vendor/phiki/phiki/resources/grammars/nushell.json',
        'vendor/phiki/phiki/resources/grammars/objective-cpp.json',
        'vendor/phiki/phiki/resources/grammars/ocaml.json',
        'vendor/phiki/phiki/resources/grammars/pascal.json',
        'vendor/phiki/phiki/resources/grammars/pkl.json',
        'vendor/phiki/phiki/resources/grammars/plsql.json',
        'vendor/phiki/phiki/resources/grammars/po.json',
        'vendor/phiki/phiki/resources/grammars/polar.json',
        'vendor/phiki/phiki/resources/grammars/postcss.json',
        'vendor/phiki/phiki/resources/grammars/powerquery.json',
        'vendor/phiki/phiki/resources/grammars/prisma.json',
        'vendor/phiki/phiki/resources/grammars/prolog.json',
        'vendor/phiki/phiki/resources/grammars/proto.json',
        'vendor/phiki/phiki/resources/grammars/puppet.json',
        'vendor/phiki/phiki/resources/grammars/purescript.json',
        'vendor/phiki/phiki/resources/grammars/qml.json',
        'vendor/phiki/phiki/resources/grammars/qmldir.json',
        'vendor/phiki/phiki/resources/grammars/qss.json',
        'vendor/phiki/phiki/resources/grammars/racket.json',
        'vendor/phiki/phiki/resources/grammars/razor.json',
        'vendor/phiki/phiki/resources/grammars/reg.json',
        'vendor/phiki/phiki/resources/grammars/rel.json',
        'vendor/phiki/phiki/resources/grammars/riscv.json',
        'vendor/phiki/phiki/resources/grammars/rosmsg.json',
        'vendor/phiki/phiki/resources/grammars/sas.json',
        'vendor/phiki/phiki/resources/grammars/scheme.json',
        'vendor/phiki/phiki/resources/grammars/sdbl.json',
        'vendor/phiki/phiki/resources/grammars/shaderlab.json',
        'vendor/phiki/phiki/resources/grammars/shellsession.json',
        'vendor/phiki/phiki/resources/grammars/smalltalk.json',
        'vendor/phiki/phiki/resources/grammars/solidity.json',
        'vendor/phiki/phiki/resources/grammars/soy.json',
        'vendor/phiki/phiki/resources/grammars/sparql.json',
        'vendor/phiki/phiki/resources/grammars/splunk.json',
        'vendor/phiki/phiki/resources/grammars/ssh-config.json',
        'vendor/phiki/phiki/resources/grammars/stata.json',
        'vendor/phiki/phiki/resources/grammars/svelte.json',
        'vendor/phiki/phiki/resources/grammars/system-verilog.json',
        'vendor/phiki/phiki/resources/grammars/systemd.json',
        'vendor/phiki/phiki/resources/grammars/talonscript.json',
        'vendor/phiki/phiki/resources/grammars/tasl.json',
        'vendor/phiki/phiki/resources/grammars/tcl.json',
        'vendor/phiki/phiki/resources/grammars/templ.json',
        'vendor/phiki/phiki/resources/grammars/terraform.json',
        'vendor/phiki/phiki/resources/grammars/toml.json',
        'vendor/phiki/phiki/resources/grammars/ts-tags.json',
        'vendor/phiki/phiki/resources/grammars/tsv.json',
        'vendor/phiki/phiki/resources/grammars/turtle.json',
        'vendor/phiki/phiki/resources/grammars/twig.json',
        'vendor/phiki/phiki/resources/grammars/typespec.json',
        'vendor/phiki/phiki/resources/grammars/typst.json',
        'vendor/phiki/phiki/resources/grammars/v.json',
        'vendor/phiki/phiki/resources/grammars/vala.json',
        'vendor/phiki/phiki/resources/grammars/verilog.json',
        'vendor/phiki/phiki/resources/grammars/vhdl.json',
        'vendor/phiki/phiki/resources/grammars/viml.json',
        'vendor/phiki/phiki/resources/grammars/vue.json',
        'vendor/phiki/phiki/resources/grammars/vue-directives.json',
        'vendor/phiki/phiki/resources/grammars/vue-html.json',
        'vendor/phiki/phiki/resources/grammars/vue-interpolations.json',
        'vendor/phiki/phiki/resources/grammars/vue-sfc-style-variable-injection.json',
        'vendor/phiki/phiki/resources/grammars/vue-vine.json',
        'vendor/phiki/phiki/resources/grammars/vyper.json',
        'vendor/phiki/phiki/resources/grammars/wasm.json',
        'vendor/phiki/phiki/resources/grammars/wenyan.json',
        'vendor/phiki/phiki/resources/grammars/wgsl.json',
        'vendor/phiki/phiki/resources/grammars/wikitext.json',
        'vendor/phiki/phiki/resources/grammars/wit.json',
        'vendor/phiki/phiki/resources/grammars/wolfram.json',
        'vendor/phiki/phiki/resources/grammars/zenscript.json',
        'vendor/phiki/phiki/resources/grammars/zig.json',

        // Phiki themes — only github-light and github-dark are used.
        'vendor/phiki/phiki/resources/themes/andromeeda.json',
        'vendor/phiki/phiki/resources/themes/aurora-x.json',
        'vendor/phiki/phiki/resources/themes/ayu-dark.json',
        'vendor/phiki/phiki/resources/themes/catppuccin-frappe.json',
        'vendor/phiki/phiki/resources/themes/catppuccin-latte.json',
        'vendor/phiki/phiki/resources/themes/catppuccin-macchiato.json',
        'vendor/phiki/phiki/resources/themes/catppuccin-mocha.json',
        'vendor/phiki/phiki/resources/themes/dark-plus.json',
        'vendor/phiki/phiki/resources/themes/dracula.json',
        'vendor/phiki/phiki/resources/themes/dracula-soft.json',
        'vendor/phiki/phiki/resources/themes/everforest-dark.json',
        'vendor/phiki/phiki/resources/themes/everforest-light.json',
        'vendor/phiki/phiki/resources/themes/github-dark-default.json',
        'vendor/phiki/phiki/resources/themes/github-dark-dimmed.json',
        'vendor/phiki/phiki/resources/themes/github-dark-high-contrast.json',
        'vendor/phiki/phiki/resources/themes/github-light-default.json',
        'vendor/phiki/phiki/resources/themes/github-light-high-contrast.json',
        'vendor/phiki/phiki/resources/themes/gruvbox-dark-hard.json',
        'vendor/phiki/phiki/resources/themes/gruvbox-dark-medium.json',
        'vendor/phiki/phiki/resources/themes/gruvbox-dark-soft.json',
        'vendor/phiki/phiki/resources/themes/gruvbox-light-hard.json',
        'vendor/phiki/phiki/resources/themes/gruvbox-light-medium.json',
        'vendor/phiki/phiki/resources/themes/gruvbox-light-soft.json',
        'vendor/phiki/phiki/resources/themes/houston.json',
        'vendor/phiki/phiki/resources/themes/kanagawa-dragon.json',
        'vendor/phiki/phiki/resources/themes/kanagawa-lotus.json',
        'vendor/phiki/phiki/resources/themes/kanagawa-wave.json',
        'vendor/phiki/phiki/resources/themes/laserwave.json',
        'vendor/phiki/phiki/resources/themes/light-plus.json',
        'vendor/phiki/phiki/resources/themes/material-theme.json',
        'vendor/phiki/phiki/resources/themes/material-theme-darker.json',
        'vendor/phiki/phiki/resources/themes/material-theme-lighter.json',
        'vendor/phiki/phiki/resources/themes/material-theme-ocean.json',
        'vendor/phiki/phiki/resources/themes/material-theme-palenight.json',
        'vendor/phiki/phiki/resources/themes/min-dark.json',
        'vendor/phiki/phiki/resources/themes/min-light.json',
        'vendor/phiki/phiki/resources/themes/monokai.json',
        'vendor/phiki/phiki/resources/themes/night-owl.json',
        'vendor/phiki/phiki/resources/themes/nord.json',
        'vendor/phiki/phiki/resources/themes/one-dark-pro.json',
        'vendor/phiki/phiki/resources/themes/one-light.json',
        'vendor/phiki/phiki/resources/themes/plastic.json',
        'vendor/phiki/phiki/resources/themes/poimandres.json',
        'vendor/phiki/phiki/resources/themes/red.json',
        'vendor/phiki/phiki/resources/themes/rose-pine.json',
        'vendor/phiki/phiki/resources/themes/rose-pine-dawn.json',
        'vendor/phiki/phiki/resources/themes/rose-pine-moon.json',
        'vendor/phiki/phiki/resources/themes/slack-dark.json',
        'vendor/phiki/phiki/resources/themes/slack-ochin.json',
        'vendor/phiki/phiki/resources/themes/snazzy-light.json',
        'vendor/phiki/phiki/resources/themes/solarized-dark.json',
        'vendor/phiki/phiki/resources/themes/solarized-light.json',
        'vendor/phiki/phiki/resources/themes/synthwave-84.json',
        'vendor/phiki/phiki/resources/themes/tokyo-night.json',
        'vendor/phiki/phiki/resources/themes/vesper.json',
        'vendor/phiki/phiki/resources/themes/vitesse-black.json',
        'vendor/phiki/phiki/resources/themes/vitesse-dark.json',
        'vendor/phiki/phiki/resources/themes/vitesse-light.json',
    ],

    'android' => [
        'gradle_jdk_path' => env('NATIVEPHP_GRADLE_PATH'),
        'android_sdk_path' => env('NATIVEPHP_ANDROID_SDK_LOCATION'),
        'emulator_path' => env('ANDROID_EMULATOR'),
        '7zip-location' => env('NATIVEPHP_7ZIP_LOCATION', 'C:\\Program Files\\7-Zip\\7z.exe'),

        /*
        |--------------------------------------------------------------------------
        | Status Bar Style
        |--------------------------------------------------------------------------
        |
        | Set the color of the status bar and navigation bar icons.
        | Options: 'auto'  - Auto-detect from system theme (recommended)
        |          'light' - Light/white icons
        |          'dark'  - Dark icons
        |
        */
        'status_bar_style' => env('NATIVEPHP_ANDROID_STATUS_BAR_STYLE', 'auto'),

        /*
        |--------------------------------------------------------------------------
        | Android Build Configuration
        |--------------------------------------------------------------------------
        |
        | These options control how your Android app is built and optimized.
        | The defaults maintain current behavior while allowing customization
        | for production builds, debugging, and app store optimization.
        |
        */
        'build' => [
            // R8/ProGuard Configuration - currently disabled
            'minify_enabled' => env('NATIVEPHP_ANDROID_MINIFY_ENABLED', false),
            'shrink_resources' => env('NATIVEPHP_ANDROID_SHRINK_RESOURCES', false),
            'obfuscate' => env('NATIVEPHP_ANDROID_OBFUSCATE', false),

            // Debug Symbol Configuration - currently enabled
            'debug_symbols' => env('NATIVEPHP_ANDROID_DEBUG_SYMBOLS', 'FULL'),
            'generate_mapping_files' => env('NATIVEPHP_ANDROID_MAPPING_FILES', false),
            'mapping_file_path' => env('NATIVEPHP_ANDROID_MAPPING_PATH', 'build/outputs/mapping/release/'),

            // ProGuard Rules - currently disabled
            'keep_line_numbers' => env('NATIVEPHP_ANDROID_KEEP_LINE_NUMBERS', false),
            'keep_source_file' => env('NATIVEPHP_ANDROID_KEEP_SOURCE_FILE', false),
            'custom_proguard_rules' => env('NATIVEPHP_ANDROID_CUSTOM_PROGUARD_RULES', []),

            // Build Performance - using Gradle defaults
            'parallel_builds' => env('NATIVEPHP_ANDROID_PARALLEL_BUILDS', true),
            'incremental_builds' => env('NATIVEPHP_ANDROID_INCREMENTAL_BUILDS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hot Reload Configuration
    |--------------------------------------------------------------------------
    */
    'hot_reload' => [
        'watch_paths' => [
            'app',
            'resources',
            'routes',
            'config',
            'public',
        ],

        'exclude_patterns' => [
            '\.git',
            'storage',
            'tests',
            'nativephp',
            'credentials',
            'node_modules',
            '\.swp',
            '\.tmp',
            '~',
            '\.log',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | App Store Connect API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for uploading apps to App Store Connect using the API.
    | These values are used for automated uploads during the package process.
    | Store sensitive data in environment variables for security.
    |
    */
    'app_store_connect' => [
        'api_key' => env('APP_STORE_API_KEY'),
        'api_key_id' => env('APP_STORE_API_KEY_ID'),
        'api_issuer_id' => env('APP_STORE_API_ISSUER_ID'),
        'app_name' => env('APP_STORE_APP_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Here you may enable or disable specific native features for your app.
    | Setting a permission to true allows NativePHP to request the necessary
    | access from the operating system at runtime (e.g., for NFC, biometrics,
    | or push notifications).
    |
    | For iOS, you can also provide a custom string that explains why your
    | app needs this permission. This text will be shown to users when they
    | are prompted to grant access. If you provide a string, the permission
    | will be enabled automatically.
    |
    | Android will interpret any string value as 'true', but the custom text
    | is only used on iOS (Android doesn't support permission reasons).
    |
    | Examples:
    |   'camera' => true,  // Uses default message
    |   'camera' => 'We need camera access to scan QR codes for login.',
    |   'camera' => false, // Permission disabled
    |
    | Make sure you run `php artisan native:install --force` after changing.
    |
    */

    'permissions' => [
        'biometric' => false,
        'camera' => false,
        'microphone' => false,
        'microphone_background' => false,
        'push_notifications' => false,
        'location' => false,
        'vibrate' => false,
        'storage_read' => false,
        'storage_write' => false,
        'scanner' => false,
        'network_state' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | iPad Support
    |--------------------------------------------------------------------------
    |
    | Enable or disable iPad support for your iOS app. When enabled, your app
    | will support iPad devices and all iPad orientations (portrait, upside down,
    | landscape left, and landscape right) as required by Apple's App Store
    | guidelines. When disabled, your app will be iPhone-only.
    |
    | Note: Once an app is deployed to the App Store with iPad
    | support you cannot revoke this action.
    |
    */
    'ipad' => false,

    /*
    |--------------------------------------------------------------------------
    | Device Orientation Support
    |--------------------------------------------------------------------------
    |
    | Configure which orientations your app supports on different devices.
    | This will be applied during the build process to set appropriate
    | constraints in Info.plist (iOS) and AndroidManifest.xml (Android).
    |
    | For iPhone and Android, you can configure specific orientations.
    | For iPad, when enabled above, all orientations are automatically supported
    | as required by Apple's App Store guidelines.
    |
    | If all orientations are false for iPhone, the build will fail with a
    | helpful error message. If all orientations are false for Android, the
    | build will fail with a helpful error message.
    |
    */
    'orientation' => [
        'iphone' => [
            'portrait' => true,
            'upside_down' => false,
            'landscape_left' => false,
            'landscape_right' => false,
        ],
        'android' => [
            'portrait' => true,
            'upside_down' => false,
            'landscape_left' => false,
            'landscape_right' => false,
        ],
    ],
];
