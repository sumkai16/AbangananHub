import './bootstrap';
import './echo';
import './field-validation';
import { aiSearch, aiSearchBox } from './ai-search';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('aiSearch', aiSearch);
Alpine.data('aiSearchBox', aiSearchBox);

Alpine.start();
