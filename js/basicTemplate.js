var m_leftMenu = {activeSubMenus: 0, categories: null, assemblies_obj: null, object: null};
var m_content = null;
var m_warehouse = null;

function initTemplate(){
	require([
        "dijit/layout/BorderContainer", "dijit/layout/ContentPane",
        "dijit/layout/SplitContainer", "dijit/form/FilteringSelect",
        "dijit/form/ComboButton", "dijit/form/Button", "dijit/Menu",
        "dijit/MenuItem", "dijit/CheckedMenuItem"
    ], function(BorderContainer, ContentPane, SplitContainer,
                FilteringSelect, ComboButton, Button,
                Menu, MenuItem, CheckedMenuItem
               ){
        /************************
         * BASIC GLOBAL OBJECTS *
         ************************/
        // Holds data (e.g. stores) and creates them on demand
        m_warehouse = new Warehouse();
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
            store: m_warehouse.get_categories(),
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
                m_content.destroyDescendants(false);
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
                while (m_content.getChildren().length > 0) {
                    console.log("REMOVE");
                    m_content.removeChild(0);
                }
                m_content.set('content', '');
                var mod = m_warehouse.get('categories_edit');
                if (mod == null) {
                    mod = new EditModule('Categories');
                    m_warehouse.set('categories_edit', mod);
                }
                mod.show();
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
            "dijit/Tree", "dijit/tree/ForestStoreModel",
            "dojo/data/ObjectStore",
			"dijit/MenuBar", "dijit/MenuBarItem"],
            function( Tree, ForestStoreModel, ObjectStore, MenuBar, MenuBarItem) {
            var dataStore = new ObjectStore({objectStore: m_warehouse.get_categories()});
			var model = new ForestStoreModel({
                store: dataStore,
				query: { 'parent' : 0},
                labelAttr: 'name',
                mayHaveChildren: function(object) {
                    if (this['node_' + object.id + '_has_children'] === false)
                        return false;
                    return true;
                },
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
						var sc = parentItem.id;
						this.store.fetch({
							query: {"parent": sc},
							onComplete: dojo.hitch(this, function(items){
								if (items == null)
                                    this['node_' + parentItem.id + '_has_children'] = false;
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
            _this.submenu.object.addChild(menubar);
            _this.submenu.object.addChild(tree);
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
		require(["dojo/html", "dijit/layout/ContentPane"],
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
function EditModule(type) {
    this.type = type;
}
EditModule.prototype.show = function() {
    if (this.object) {
        m_content.addChild(this.object);
        this.object.startup();
    } else {
        require(['dgrid/dgrid'], dojo.hitch(this, function() {
            require([   'dojo/_base/declare', 'dgrid/OnDemandGrid',
                        'dgrid/Keyboard', 'dgrid/Selection',
                        'dgrid/extensions/DijitRegistry', 'dijit/form/Button'
            ], dojo.hitch(this, function (
                declare, OnDemandGrid,
                Keyboard, Selection, DijitRegistry, Button
            ) {
                this.object = new (declare([ OnDemandGrid, Keyboard, Selection]))({
                    store: m_warehouse['get_' + this.type.toLowerCase()](),
                    query: {parent: 0},
                    loadingMessage: "Loading data...",
                    allowSelectAll: true,
                    columns: this.get_columns()
                });
                var remove = new Button({label: 'Ausgewählte löschen'});
                var save = new Button({label: 'Ausgewählte speichern'});
                var reset = new Button({label: 'Ausgewählte zurücksetzen'});
                m_content.addChild(this.object);
                m_content.addChild(remove);
                m_content.addChild(save);
                m_content.addChild(reset);
                remove.startup();
                save.startup();
                reset.startup();
                this.object.startup();
            }));
        }));
    }
}
EditModule.prototype.get_columns = function() {
    require(['dgrid/editor',
            'dgrid/selector',
            'dgrid/tree'
            ], dojo.hitch(this, function(editor, selector, tree)
    {
        switch(this.type) {
            case 'Categories':
                this.columns = [
                selector({ className: "selector" }),
                tree({  label: "Name",
                    field:"name",
                    sortable: true
                }),
                editor({   label: "Footprints deaktivieren",
                    field: "footprints",
                    sortable: false,
                    editorArgs: { disabled: true },
                    editor: "checkbox"
                }),
                editor({   label: "Hersteller deaktivieren",
                    field: "manufacturers",
                    sortable: false,
                    editorArgs: { disabled: true },
                    editor: "checkbox"
                }),
                editor({   label: "Automatische Links zu Datenblättern deaktivieren",
                    field: "autodatasheets",
                    sortable: false,
                    editorArgs: { disabled: true },
                    editor: "checkbox"
                })
                ];
                break;
            default:
                this.columns = [
                selector({ className: "selector" }),
                {   renderExpando: true,
                    label: "Name",
                    field:"name",
                    sortable: true
                }];
        }
    }));
    return this.columns;
}

// On demand common data
function Warehouse() {}
Warehouse.prototype.store = [];
Warehouse.prototype.get = function(name) {
    if (!name)
        throw "No name given";
    if (name in this.store)
        return this.store[name];
    return null;
}
Warehouse.prototype.set = function(name, object) {
    if (!name)
        throw "No name given";
    this.store[name] = object;
}
Warehouse.prototype._get_store = function(target_table)
{
    var store;
    require(['dojo/store/JsonRest', 'dojo/store/Cache', 'dojo/store/Memory'],
            function (JsonRest, Cache, Memory)
    {
        store = new Cache(JsonRest({
            target: 'api.php/' + target_table,
            sortParam: 'sortedBy',
            ascendingPrefix: '%2B', //JsonRest does not urlencode the + to %2B (on the server + is decoded to a space)
            mayHaveChildren: function(item) {
                if (this['item_' + item.id + '_has_children'] === 'no')
                    return false;
                return true;
            },
            getChildren: function (parentItem, options) {
                var children = this.query({parent: parentItem.id}, options);
                if (!children)
                    this['item_' + parentItem.id + '_has_children'] = 'no';
                return children;
            }
        }), Memory());
    });
    return store;
}
Warehouse.prototype.get_devices = function() {
    if (!this._devices)
        this._devices = this._get_store('Devices');
    return this._categories;
}
Warehouse.prototype.get_categories = function() {
    if (!this._categories)
       this._categories = this._get_store('Categories');
    return this._categories;
}