import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  // Relative asset paths so the build works from any web root or sub-folder.
  base: './',
  test: {
    environment: 'node',
    include: ['src/**/*.test.js'],
  },
})
