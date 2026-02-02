import { readFileSync } from 'fs';
import path from 'path';

export function loadScript(relativePath) {
  const fullPath = path.resolve(process.cwd(), relativePath);
  const scriptContent = readFileSync(fullPath, 'utf-8');
  window.eval(scriptContent);
}
