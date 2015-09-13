var m_leftMenu = {activeSubMenus: 0, categories: null, assemblies_obj: null, object: null};
var m_content = null;
var m_category_store = {store: null, adapter: null};

function initTemplate(){
	require([
        "dojo/store/JsonRest", "dstore/legacy/StoreAdapter",
        "dijit/layout/BorderContainer", "dijit/layout/ContentPane",
        "dijit/layout/SplitContainer", "dijit/form/FilteringSelect",
        "dijit/form/ComboButton", "dijit/form/Button", "dijit/Menu",
        "dijit/MenuItem", "dijit/CheckedMenuItem"
    ], function(JsonRest, StoreAdapter, BorderContainer, ContentPane,
                SplitContainer, FilteringSelect, ComboButton, Button,
                Menu, MenuItem, CheckedMenuItem
               ){
        /************************
         * BASIC GLOBAL OBJECTS *
         ************************/
        // Main store for categories. Used by SearchBox and left menu (categories).
        m_category_store.store = new JsonRest({
            target: '/api.php/Categories',
            sortParam: 'sortedBy'
        });
        // Adapter for dojo objects:
        m_category_store.adapter = new StoreAdapter(m_category_store.store);
        // Leftmenu:
        m_leftMenu.categories = new CategoriesSubmenu();

        /*****************************************
         * BASIC LAYOUT AND MENUS                *
         *****************************************/
        // Main Layout Container
        var main_container = new BorderContainer({
            design: 'headline'
        }, 'content_container');
        // Container for main Content
        m_content = new ContentPane({
            region: 'center'
        }, 'content_main');
        m_content.startup();
        // Top menu (contains menubar...)
        var top_menu = new ContentPane({
            region: 'top'
        }, 'content_top');
        top_menu.startup();
        main_container.startup();
        
        // Left menu (used for navigation through categories etc)
        m_leftMenu.object =  new SplitContainer({
            region: 'left',
            splitter: true,
            orientation: 'vertical',
            activeSizing: true,
            'class': 'leftMenu'
        });

        /***************
         * MENU-ITEMS  *
         ***************/
        // TOP MENU
        // Live-search for items
        var search_box = new FilteringSelect({
            store: m_category_store.store,
            hasDownArrow: false,
            required: false,
            searchAttr: 'name',
            pageSize: 100,
            placeHolder: 'Suche nach...',
            invalidMessage: 'Kein Ergebnis gefunden.'
        }, 'search_box');
        search_box.startup();
        // search parameters
        var menu_ss = new Menu();
        menu_ss.addChild(new CheckedMenuItem({
            label: 'Namen'
        }));
        menu_ss.addChild(new CheckedMenuItem({
            label: 'Beschreibung'
        }));
        var search_settings =  new ComboButton({
            dropDown: menu_ss,
            iconClass:'icon_preferences',
            showLabel: false,
            onClick: function() {
                this.toggleDropDown();
            }
        }, 'search_settings');
        menu_ss.startup();
        search_settings.startup();
        // View (left menu settings):
        var menu_view = new Menu();
        menu_view.addChild(new CheckedMenuItem({
            label: 'Baugruppen',
            onClick: function() {
                showAssemblies();
                m_leftMenu.object.startup();
            }
        }));
        menu_view.addChild(new CheckedMenuItem({
            label: 'Kategorien',
            checked: true,
            onClick: function() {
                m_leftMenu.categories.toggle();
                m_leftMenu.object.startup();
            }
        }));
        var view_button = new ComboButton({
            dropDown: menu_view,
            onClick: function() {
                this.toggleDropDown();
            }
        }, 'view_button');
        menu_view.startup();
        view_button.startup();
        // Help Button
        var help_button = new Button({
            onClick: function() {
                m_content.set('content', 
                              "<iframe style='border: none; width: 100%; height: 100%;' src='documentation/dokuwiki/doku.php'/>"
                );
            }
        }, 'help_button');
        // Edit menu:
        var menu_edit = new Menu();
        menu_edit.addChild(new MenuItem({
            label: 'Kategorien',
            onClick: function() {
                m_content.set('content', '<div id="content_div"></div>');
                var a = new EditModule('Categories', 'content_div');
                a.show();
            }
        }));
        menu_edit.addChild(new MenuItem({
            label: 'Baugruppen'
        }));
        var edit_button = new ComboButton({
            dropDown: menu_edit,
            onClick: function() {
                this.toggleDropDown();
            }
        }, 'edit_button');
        menu_edit.startup();
        edit_button.startup();
        // Default actions:
        m_leftMenu.categories.show();
	});
}

function showAssemblies() {
	showAssemblies.count = ++showAssemblies.count || 0;
	if (m_leftMenu.assemblies_obj == null) {
		m_leftMenu.assemblies_obj = new SubMenu("Assemblies", null);
	}
	if (showAssemblies.count % 2 == 1) {
		m_leftMenu.assemblies_obj.hide();
	} else {
		m_leftMenu.assemblies_obj.show();
	}
	handleLeftMenue(showAssemblies.count % 2 == 0);
}

// Decide weather to show or to hide the menu (or to do nothing)
// Keep track of open submenues
function handleLeftMenue(show) {
	m_leftMenu.activeSubMenus = m_leftMenu.activeSubMenus + (show == true ? 1 : -1);
	require(["dijit/registry"], function(registry){
		if (m_leftMenu.activeSubMenus == 1) {
			registry.byId("content_container").addChild(m_leftMenu.object);
		} else if (m_leftMenu.activeSubMenus == 0) {
			registry.byId("content_container").removeChild(m_leftMenu.object);
		}
	});
}

// SubMenu class for categories, inheritates from SubMenu class
function CategoriesSubmenu() {
	this.submenu = new SubMenu("categories");
}
CategoriesSubmenu.prototype.isVisible = false;
CategoriesSubmenu.prototype.initializied = false;
CategoriesSubmenu.prototype.hide = function() {
	if (this.isVisible == true) {
		this.submenu.hide();
		this.isVisible = false;
		handleLeftMenue(false);
	}
};
CategoriesSubmenu.prototype.show = function() {
	this.submenu.show();
	if (this.initializied == false) {
		var _this = this;
        require([
            'dijit/registry',
			"dijit/Tree", "dijit/tree/ForestStoreModel",
            "dojo/data/ObjectStore",
			"dijit/MenuBar", "dijit/MenuBarItem"],
            function(registry, Tree, ForestStoreModel, ObjectStore, MenuBar, MenuBarItem) {
            var dataStore = new ObjectStore({objectStore: m_category_store.store});
			var model = new ForestStoreModel({
                store: dataStore,
				query: { 'parent' : 0},
                labelAttr: 'name',
                mayHaveChildren: function(object) {return true;},
				getChildren : function(parentItem, callback, onError) {
					if (parentItem.root == true) {
						this.store.fetch({
							query: this.query,
							onComplete: dojo.hitch(this, function(items){
								this.root.children = items;
								callback(items);
							}),
							onError: onError
						});
					} else {
						var sc = this.store.getValue(parentItem, "id");
						this.store.fetch({
							query: {"parent": sc},
							onComplete: dojo.hitch(this, function(items){
								if (items == []) this.root.root = false;
								this.root.children = items;
								callback(items);
							}),
							onError: onError
						});
					}
				}
			});
			var tree = new Tree({
				  model: model,
				  showRoot: false,
				  autoExpand: false,
				  onClick: function(item){
					  // Get the URL from the item, and navigate to it
					  console.log(dataStore.getValue(item, "id"));
				  }
			});
			var menubar = new MenuBar();
			var exp = new MenuBarItem({
				onClick: function() {tree.expandAll();},
				label: 'Expand'
			});
			var col = new MenuBarItem({
				onClick: function() {tree.collapseAll();},
				label: 'Collapse',
				'class': 'right'
			});
			menubar.addChild(exp);
			menubar.addChild(col);
			registry.byId('submenu_' + _this.submenu.name).addChild(menubar);
			registry.byId('submenu_' + _this.submenu.name).addChild(tree);
			menubar.startup();
			tree.startup();
		});
		this.initializied = true;
	}
	this.isVisible = true;
	handleLeftMenue(true);
}
CategoriesSubmenu.prototype.toggle = function() {
	if (this.isVisible == true)
		this.hide();
	else
		this.show();
}

//SubMenu Class
function SubMenu(name) {
	this.name = name;
	this.object = null;
}
SubMenu.prototype.show = function() {
	if (this.object == null) {
		var _this = this;
		require([	"dojo/html", "dijit/layout/ContentPane"],
		  function(html, ContentPane) {
				// Create object (dijit widget)
				_this.object = new ContentPane({
					id: 'submenu_' + _this.name,
					'class': 'submenu'
				});
				html.set(_this.object.get("domNode"), "<div class='submenu_title'>" + _this.name + "</div>");
			});
		this.object = _this.object;
	}
	m_leftMenu.object.addChild(this.object);
}
SubMenu.prototype.hide = function() {
	if (this.object != null) {
		m_leftMenu.object.removeChild(this.object);
	}
}

//EditModule Class
function EditModule(type, dom_id) {
    this.type = type;
    this.dom_id = dom_id;
    this.is_set = false;
}
EditModule.prototype.show = function() {
    if (this.is_set == false) {
        var _this = this;
        require(['dgrid/dgrid'], function() {
            require([   "dojo/store/JsonRest", 'dojo/_base/declare', 'dgrid/OnDemandGrid',
                        'dgrid/Keyboard', 'dgrid/Selection', 'dgrid/Tree', 'dgrid/Editor',
                        'dstore/legacy/StoreAdapter', "dgrid/Selection", "dgrid/Selector"],
                    function (JsonRest, declare, OnDemandGrid,
                              Keyboard, Selection, Tree, Editor,
                              StoreAdapter, Selection, Selector
                             ) {
                    var store = new JsonRest({
                        target: '/api.php/' + _this.type,
                        idProperty: 'id',
                        sortParam: "sortBy",
                        getChildren: function(parentItem, options) {
                            return this.query({parent: this.getIdentity(parentItem)}, options);
                        }
                    });
                    var mstore = new StoreAdapter({
                        objectStore: store,
                        getChildren: function(parentItem) {
                            return this.filter({parent: parentItem.id});
                        }
                    });
                    var treeGrid = new (declare([ OnDemandGrid, Keyboard, Selection, Selector, Editor, Tree ]))({
                        collection: mstore.filter({parent: 0}),
                        loadingMessage: "Loading data...",
                        //store: mstore,
                        selectionMode: "none",
                        allowSelectAll: true,
                        columns: [
                            {selector: 'checkbox', className: "selector"},
                            {renderExpando: true, label: "Name", field:"name", sortable: true},
                            {label: "Footprints deaktivieren", field: "footprints", sortable: false, editor: "checkbox"}
                        ]
                    }, _this.dom_id);
                    treeGrid.startup();
            });
        });
        this.is_set = true;
    }
}