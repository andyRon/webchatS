const mix = require('laravel-mix');


mix.js('resources/js/app.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css')
    .styles([
        'resources/css/reset.css',
        'public/css/app.css',
        'node_modules/muse-ui/dist/muse-ui.css'
    ], 'public/css/app.css');
