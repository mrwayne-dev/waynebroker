import type { GlobalProvider } from '@ladle/react';
import { useEffect } from 'react';

import '../resources/css/app.css';

/**
 * Wraps every story.
 *
 * Two jobs: pull in the real stylesheet so stories are styled by the same
 * token layer the app uses (no story-only CSS, or the stories stop being
 * evidence), and put the story on a void surface with ivory text so a
 * component is judged in the context it actually ships in.
 */
export const Provider: GlobalProvider = ({ children }) => {
    // The app hard-codes .dark on <html> in app.blade.php because Gotham is
    // dark-only. Ladle renders its own document, so the class is applied here
    // for the same reason: the starter kit's `dark:` variants need it.
    useEffect(() => {
        document.documentElement.classList.add('dark');
    }, []);

    return (
        <div className="min-h-svh bg-background p-10 text-foreground">
            {children}
        </div>
    );
};
