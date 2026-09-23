// Adds jest-dom's DOM matchers (toBeInTheDocument, toHaveAttribute, ...) to
// Vitest's expect. The /vitest entry point registers against Vitest rather
// than Jest's globals.
import '@testing-library/jest-dom/vitest';
