import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            // Plan Section 15.2: two families, no third. Inter carries
            // display and body; JetBrains Mono carries every number so
            // tabular figures line up in tables and tickers.
            // 700 on Inter exists for the one reserved gesture — hero
            // display type at very large size.
            fonts: [
                google('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                google('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            // Maveren, kept for reference during the strangler port. It is
            // read-only by policy, it is not shipped, and linting it produced
            // 112 warnings about code we are deleting rather than fixing.
            '_legacy/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            // Read-only reference: `vp check --fix` rewrote 47 files in here,
            // including minified vendor bundles, before this entry existed.
            '_legacy/**',
            // Wayfinder regenerates these on every build in its own style, so
            // formatting them is a fight that repeats forever. They are
            // already excluded from lint for the same reason.
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            // The plan and the Maveren audit are prose the human owns and
            // edits directly; reflowing their tables is not ours to do.
            'docs/**',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
