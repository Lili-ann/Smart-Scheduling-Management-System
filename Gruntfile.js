// Grunt is a small build tool.
// This file tells Grunt what tasks it can run for this project.
module.exports = function (grunt) {
    grunt.initConfig({
        // Read project information from package.json.
        pkg: grunt.file.readJSON('package.json'),

        // Check that manifest.json is valid JSON.
        jsonlint: {
            manifest: {
                src: ['manifest.json']
            }
        },

        // Make a smaller CSS file and save it inside the dist folder.
        cssmin: {
            options: {
                level: 1
            },
            target: {
                files: {
                    'dist/styles.min.css': ['styles.css']
                }
            }
        },

        // Make a smaller service worker JavaScript file.
        uglify: {
            serviceWorker: {
                files: {
                    'dist/sw.min.js': ['sw.js']
                }
            }
        },

        // Watch these files and rebuild automatically when one changes.
        watch: {
            assets: {
                files: ['styles.css', 'sw.js', 'manifest.json'],
                tasks: ['default']
            }
        }
    });

    // Load the Grunt plugins listed in package.json.
    grunt.loadNpmTasks('grunt-jsonlint');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');

    // The default task runs when you type: npm run build
    grunt.registerTask('default', ['jsonlint', 'cssmin', 'uglify']);
};
