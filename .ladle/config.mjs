/**
 * Ladle, not Storybook.
 *
 * Storybook 10.6 declares `peerOptional vite-plus@"^0.1.15 || ^0.2.0"` and this
 * scaffold runs vite-plus 0.3.0, so it refuses to install. Ladle needs only
 * React >= 18 and carries its own nested Vite 6, which leaves the application's
 * Vite 8 toolchain untouched.
 *
 * Consequence worth knowing: stories are rendered by Ladle's Vite, not by the
 * app's build. Tailwind is wired into it separately in .ladle/vite.config.ts.
 */
export default {
    stories: 'resources/js/**/*.stories.{js,jsx,ts,tsx}',
    /**
     * Without this, Ladle loads the application's root vite.config.ts, which
     * drags the Laravel plugin, the font downloader and Wayfinder's type
     * generation into every story build — none of which a component story
     * needs, and Wayfinder rewriting generated files as a side effect of
     * running Ladle is worse than noise. .ladle/vite.config.ts carries just
     * Tailwind and the `@/*` alias.
     */
    viteConfig: '.ladle/vite.config.ts',
    addons: {
        // Gotham is dark-only (plan Section 15.1). The theme toggle stays
        // enabled so a contributor can prove a component does not secretly
        // depend on the .dark class, but dark is what it opens on.
        theme: {
            enabled: true,
            defaultState: 'dark',
        },
        // Nothing here is internationalised: plan A7 drops i18n at launch.
        i18n: {
            enabled: false,
        },
        rtl: {
            enabled: false,
        },
    },
};
