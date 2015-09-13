var profile = (function(){
    return {
        basePath: "./",
        releaseDir: "./release",
        releaseName: "lib",
        action: "release",
        layerOptimize: "closure",
        optimize: "closure",
        cssOptimize: "comments",
        mini: true,
        stripConsole: "warn",
        selectorEngine: "lite",

        defaultConfig: {
            hasCache:{
                "dojo-built": 1,
                "dojo-loader": 1,
                "dom": 1,
                "host-browser": 1,
                "config-selectorEngine": "lite"
            },
            parseOnLoad: 0,
            async: 1
        },

        staticHasFeatures: {
            "config-deferredInstrumentation": 0,
            "config-dojo-loader-catches": 0,
            "config-tlmSiblingOfDojo": 0,
            "dojo-amd-factory-scan": 0,
            "dojo-combo-api": 0,
            "dojo-config-api": 1,
            "dojo-config-require": 0,
            "dojo-debug-messages": 0,
            "dojo-dom-ready-api": 1,
            "dojo-firebug": 0,
            "dojo-guarantee-console": 1,
            "dojo-has-api": 1,
            "dojo-inject-api": 1,
            "dojo-loader": 1,
            "dojo-log-api": 0,
            "dojo-modulePaths": 0,
            "dojo-moduleUrl": 0,
            "dojo-publish-privates": 0,
            "dojo-requirejs-api": 0,
            "dojo-sniff": 1,
            "dojo-sync-loader": 0,
            "dojo-test-sniff": 0,
            "dojo-timeout-api": 0,
            "dojo-trace-api": 0,
            "dojo-undef-api": 0,
            "dojo-v1x-i18n-Api": 1,
            "dom": 1,
            "host-browser": 1,
            "extend-dojo": 1
        },

        packages: [
        'dojo', 'dijit', 'dgrid', 'dstore', 'xstyle', 'put-selector'
        ],

        // Add new dependencies here:
        layers: {
            // Used in the main template
            'dojo/dojo': {
                include: [
                    // Basics
                    'dojo/dojo', 'dojo/ready', 'dojo/_base/declare',
                    // Start modifying here:
               'dijit/Tree', 'dijit/tree/ForestStoreModel', 'dojo/data/ObjectStore', 'dijit/MenuBar',
               'dijit/MenuBarItem',
                    'dijit/layout/BorderContainer', 'dijit/layout/ContentPane',
                    'dijit/layout/SplitContainer', 'dijit/form/FilteringSelect',
                    'dijit/form/ComboButton', 'dijit/Menu', 'dijit/CheckedMenuItem',
                    'dojo/store/JsonRest', 'dstore/legacy/StoreAdapter'
                ],
               boot: true,
               customBase: true
            }
        }
    };
})();
