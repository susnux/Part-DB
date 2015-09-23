var m_leftMenu = {activeSubMenus: 0, categories: null, assemblies: null, object: null};
var m_content = null;
var m_warehouse = null;

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
window['handleLeftMenue'] = handleLeftMenue;

function initTemplate(){
	require([
	"dijit/layout/BorderContainer", "dijit/layout/ContentPane",
	"dijit/layout/SplitContainer", "dijit/form/FilteringSelect",
	"dijit/form/ComboButton", "dijit/form/Button", "dijit/Menu",
	"dijit/MenuItem", "dijit/CheckedMenuItem", 'partdb/Submenu'
	], function(BorderContainer, ContentPane, SplitContainer,
				FilteringSelect, ComboButton, Button,
				Menu, MenuItem, CheckedMenuItem, Submenu
	){
		/************************
		 * BASIC GLOBAL OBJECTS *
		 ************************/
		// Holds data (e.g. stores) and creates them on demand
		m_warehouse = new Warehouse();
		
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

		m_leftMenu.categories = new Submenu({
			store: m_warehouse.get_categories(),
			name: 'Kategorien',
			attach_node: m_leftMenu.object,
			callback: handleLeftMenue
		});
		m_leftMenu.assemblies = new Submenu({
			store: m_warehouse.get_devices(),
			name: 'Baugruppen',
			attach_node: m_leftMenu.object,
			callback: handleLeftMenue
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
				m_leftMenu.assemblies.toggle();
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
				m_content.destroyDescendants();
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
				while(m_content.hasChildren())
                    m_content.removeChild(0);
                m_content.set('content', '');
				var mod = m_warehouse.get('categories_edit');
				if (mod == null) {
					require(['partdb/partdb'], function() {
						require(['partdb/EditModule'], function(EditModule){
							mod = new EditModule({
								attach_node: m_content,
								store: m_warehouse.get_categories(),
								item_template: [{
										name: 'name',
										type: 'text',
										label: 'Name: '
									},{
										name: 'footprints',
										type: 'bool',
										label: 'Footprints deaktivieren:'
									},{
										name: "manufacturers",
										type: 'bool',
										label: 'Hersteller deaktivieren: '
									},{
										name: "autodatasheets",
										type: 'bool',
										label: 'Automatische Links zu Datenblättern deaktivieren: '
									}
								]
							});
							m_warehouse.set('categories_edit', mod);
							mod.show();
						});
					});
				} else {
					mod.show();
				}
			}
		}));
		menu_edit.addChild(new MenuItem({
			label: 'Baugruppen',
			onClick: function() {
                while(m_content.hasChildren())
                    m_content.removeChild(0);
				m_content.set('content', '');
				var mod = m_warehouse.get('devices_edit');
				if (mod == null) {
					require(['partdb/partdb'], function() {
						require(['partdb/EditModule'], function(EditModule){
							mod = new EditModule({
								attach_node: m_content,
								store: m_warehouse.get_devices(),
								item_template: [{
									name: 'name',
									type: 'text',
									label: 'Name: '
								 }]
							});
							m_warehouse.set('devices_edit', mod);
							mod.show();
						});
					});
				} else {
					mod.show();
				}
			}
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