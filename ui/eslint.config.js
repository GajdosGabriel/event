import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import vueTsConfig from '@vue/eslint-config-typescript'

export default [
  {
    ignores: ['dist/**', 'node_modules/**', 'public/**', '*.tsbuildinfo'],
  },

  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  ...vueTsConfig(),

  {
    rules: {
      // Vue komponenty sú pomenované podľa súboru (EventCard.vue, HomePage.vue).
      // Jednoslovné názvy sú v tomto projekte zámerné a konzistentné.
      'vue/multi-word-component-names': 'off',

      // Formátovanie rieši editor, nie linter — inak by sa hlásili tisíce
      // rozdielov v existujúcich ~9k riadkoch šablón.
      'vue/max-attributes-per-line': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/html-self-closing': 'off',
      'vue/html-indent': 'off',
      'vue/html-closing-bracket-newline': 'off',
      'vue/html-closing-bracket-spacing': 'off',
      'vue/first-attribute-linebreak': 'off',
      'vue/multiline-html-element-content-newline': 'off',
      'vue/attributes-order': 'off',

      // Nepoužitá premenná je väčšinou preklep; podčiarkovník = zámerne nepoužité.
      '@typescript-eslint/no-unused-vars': [
        'warn',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' },
      ],

      // `any` je v projekte zatiaľ bežné, nechceme zaplaviť výstup chybami.
      '@typescript-eslint/no-explicit-any': 'warn',
    },
  },
]
