module.exports = {
    testEnvironment: 'jsdom',
    setupFiles: ['./tests/jest/setup.js'],
    testMatch: ['**/tests/jest/**/*.test.js'],
    collectCoverageFrom: [
        'public/js/**/*.js',
        'public/components/**/*.js'
    ]
};
