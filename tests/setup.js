import { vi } from 'vitest';
import $ from 'jquery';

globalThis.$ = $;
globalThis.jQuery = $;

globalThis.alert = vi.fn();
