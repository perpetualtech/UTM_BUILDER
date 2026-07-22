import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  globalIgnores(['dist', 'public']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      ecmaVersion: 2020,
      globals: globals.browser,
    },
    rules: {
      // Permite `const { omit, ...rest } = obj` para excluir props sin
      // marcar `omit` como no usada — patrón usado en los mocks de MSW.
      '@typescript-eslint/no-unused-vars': ['error', { ignoreRestSiblings: true }],
    },
  },
  {
    // Primitivos generados por `shadcn add` (vendored): exportan helpers
    // (buttonVariants, badgeVariants, etc.) junto al componente por
    // convención propia de shadcn — no es código nuestro a reestructurar.
    files: ['src/modules/core/components/design-system/**/*.tsx'],
    rules: {
      'react-refresh/only-export-components': 'off',
    },
  },
])
