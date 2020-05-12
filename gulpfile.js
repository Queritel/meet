var csso = require('gulp-csso');
var del = require('del');
var gulp = require('gulp');
var { watch } = require('gulp');
var htmlmin = require('gulp-htmlmin');
var uglify = require('gulp-uglify');
var gnirts = require('gulp-gnirts');
var syncy = require('syncy');
var done = require('done');
var rsync = require('gulp-rsync');
var debug = require('gulp-debug');
var license = require('gulp-header-license');
var npmGet = require('gulp-npm-dist');
var remoteSrc = require('gulp-remote-src');
var injectVersion = require('gulp-inject-version');


// External dependencies.
/////////////////////////////////
gulp.task('dependencies', function(cb) {

  var dependencies = [
	  {src: './node_modules/bootstrap/dist', dest: './src/assets/ext/bootstrap'},
	  {src: './node_modules/jquery/dist', dest: './src/assets/ext/jquery'},
	  {src: './node_modules/fullcalendar/dist', dest: './src/assets/ext/jquery-fullcalendar'},
	  {src: './node_modules/qtip2/dist', dest: './src/assets/ext/jquery-qtip'},
	  {src: './node_modules/moment/min', dest: './src/assets/ext/moment'},
	  {src: './node_modules/moment-timezone/builds', dest: './src/assets/ext/moment'},
	  {src: './node_modules/trumbowyg/dist', dest: './src/assets/ext/trumbowyg'},
	  {src: './node_modules/cookieconsent/build', dest: './src/assets/ext/cookieconsent'},
	  {src: './node_modules/flatpickr/dist', dest: './src/assets/ext/flatpickr'},
	  {src: './node_modules/select2/dist/js', dest: './src/assets/ext/select2'},
	  {src: './node_modules/select2/dist/css', dest: './src/assets/ext/select2'},
	  {src: './node_modules/select2-bootstrap-theme/dist', dest: './src/assets/ext/select2'},
	  {src: './node_modules/jquery-jeditable/dist', dest: './src/assets/ext/jquery-jeditable'},
	  {src: './node_modules/gasparesganga-jquery-loading-overlay/dist', dest: './src/assets/ext/jquery-loading-overlay'},
	  {src: './node_modules/jquery-ui-timepicker-addon/dist', dest: './src/assets/ext/jquery-ui'},
	  {src: './node_modules/flag-icon-css/css', dest: './src/assets/css/flag-icon'},
      {src: './node_modules/flag-icon-css/flags', dest: './src/assets/css/flags'},
	  {src: './node_modules/cldr-core', dest: './src/assets/ext/cldr-core'},
	  {src: './node_modules/cldrjs/dist', dest: './src/assets/ext/cldrjs-dist'},
	  {src: './node_modules/tz_mappings', dest: './src/assets/ext/tz_mappings'},
	  {src: './node_modules/cldr-dates-full/main', dest: './src/assets/ext/cldr-dates-full-main'},
	  {src: './node_modules/cldr-numbers-full/main', dest: './src/assets/ext/cldr-numbers-full-main'},
	  {src: './node_modules/cldr-localenames-full/main', dest: './src/assets/ext/cldr-localenames-full-main'},
  ]

  dependencies.map(function(dep) {
	  return gulp.src(`${dep.src}/**`)
         .pipe(debug({title: 'Gulping dependencies'}))
         .pipe(gulp.dest(`${dep.dest}/`));
  });

  cb();
});

// Version number injection.
/////////////////////////////////
gulp.task('version', function () {
    return gulp.src('./dist/application/config/config.php')
        .pipe(injectVersion())
        .pipe(gulp.dest('./dist/application/config/'));
});

// CSS files.
/////////////////////////////////
gulp.task('styles', function () {
  return gulp.src([
       './src/assets/css/**/*.css',
	   './src/assets/ext/**/*.css'
	],
    {base: 'src'})
    // Minify the file
    .pipe(csso())
    // Output
    .pipe(gulp.dest('./dist/'))
});

// JavaScript files.
/////////////////////////////////
gulp.task('scripts', function() {

	var year = (new Date()).getFullYear();

  	return gulp

	.src([
			'./src/assets/js/**/*.js',
		],
		{base: 'src'}
  	)

	// Obfuscate strings.
	// .pipe(gnirts())

    // Minify the file.
    .pipe(uglify({
		compress: {arguments: true, drop_console: true, drop_debugger: true},
		mangle: {toplevel: false},
		output: {beautify: false, comments: /^\s*!/},
		parse: {},
		nameCache: { },
		sourceMap: { },
		toplevel: false,
		warnings: false
	}))
	.pipe(license('/* Copyright (c) ${year}, Tekhnee.org (author of this fork) and/or Alex Tselegidis (author of EasyAppointments, on which this fork is based).\nSee source code at https://github.com/tekhnee/appointments for more detailed copyright notices. */', {year: year}))
	.pipe(gulp.dest('./dist/'))
});

// HTML files.
/////////////////////////////////
gulp.task('pages', function() {

  return gulp.src([
      './src/application/views/appointments/*.php',
      './src/application/views/backend/*.php',
      './src/application/errors/html/*.php'
	], {base: 'src'})

    .pipe(htmlmin({
      collapseWhitespace: false,
      removeComments: true
    }))

    .pipe(gulp.dest('./dist'));
});


// All files.
/////////////////////////////////
gulp.task('copy', function() {

	return gulp.src([
		'./src/**'
	])

	.pipe(gulp.dest('./dist/'));
})

// Clean output directory.
/////////////////////////////////
gulp.task('clean', () => del(['./dist/']));

// Watcher task.
//////////////////////////////////////////
gulp.task('sync', () => (
    syncy('./src/**/*', './dist', {
	  base: './src',
	  ignoreInDest: '**/storage/**/*',
	  updateAndDelete: true,  // If false, then syncy would only add new files, neither update nor delete.
    })
));

gulp.task ('watcher', function() {
	watch(['src/**', '!.src/storage'], gulp.series('sync'));
})

// PRODUCTION (INJECT VERSION NUMBER, MINIFY & MANGLE)
/////////////////////////////////
gulp.task('production', gulp.series(
	'clean',
	'copy', // Should be run before any minification takes place.
    'styles',
    'scripts',
    'pages',
	'version',
  )
);

// DEPLOY (UPLOAD)
/////////////////////////////////
function upload(cb) {
  return gulp.src('.')
    .pipe(rsync({
      username: '',
      hostname: '',
      destination: '',
	  exclude: ['CHANGELOG.md', 'README.md', 'CONTRIBUTING.md', 'composer.json', 'composer.lock', 'package-lock.json', 'package.json', 'gulpfile.js', 'LICENSE', '.gitignore', '.env', '.editorconfig', 'src', 'node_modules', '.git', '.vscode', '.DS_Store'],
	  incremental: true,
	  recursive: true,
	  archive: true,
	  clean: true,
      compress: true,
      silent: false,
	  command: true,
	  progress: true
    }));
}
gulp.task('deploy', upload);

// DEFAULT (WATCH)
/////////////////////////////////
gulp.task('default', gulp.series(
	'copy',
	'watcher'
  )
);
