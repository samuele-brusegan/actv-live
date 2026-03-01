module.exports = {
    testEnvironment: 'jsdom',
    setupFiles: ['./test/setup.js'],
    testMatch: ['**/test/**/*.test.js'],
    collectCoverageFrom: [
        'public/js/**/*.js',
        'public/components/**/*.js'
    ]
};
