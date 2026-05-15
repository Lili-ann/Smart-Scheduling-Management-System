module.exports = function (grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        jsonlint: {
            manifest: {
                src: ['manifest.json']
            }
        },
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
        uglify: {
            serviceWorker: {
                files: {
                    'dist/sw.min.js': ['sw.js']
                }
            }
        },
        watch: {
            assets: {
                files: ['styles.css', 'sw.js', 'manifest.json'],
                tasks: ['default']
            }
        }
    });

    grunt.loadNpmTasks('grunt-jsonlint');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');

    grunt.registerTask('default', ['jsonlint', 'cssmin', 'uglify']);
};
