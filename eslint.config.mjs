import js from "@eslint/js";
import globals from "globals";

export default [
    {
        files: ["assets/**/*.js"],
        languageOptions: {
            globals: {
                ...globals.browser,
                // Ajoute les globales Stimulus si nécessaire
            },
            ecmaVersion: 'latest',
            sourceType: "module"
        },
        rules: {
            ...js.configs.recommended.rules,
            "no-unused-vars": "warn",
            "no-console": "off",
            "semi": ["error", "always"],
            "quotes": ["error", "single"],
        }
    },
    {
        ignores: [
            "node_modules/**",
            "var/**",
            "vendor/**",
            "public/**",
            "!public/assets/**" // Sauf les assets si besoin
        ]
    }
];
