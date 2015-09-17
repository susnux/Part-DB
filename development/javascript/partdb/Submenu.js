define([
'dojo/_base/declare', "dojo/_base/lang",
"dojo/data/ObjectStore", "dojo/html",
"dijit/layout/ContentPane",
"dijit/Tree", "dijit/tree/ForestStoreModel",
"dijit/MenuBar", "dijit/MenuBarItem"
], function(
	declare, lang, ObjectStore, html,
	ContentPane, Tree, ForestStoreModel,
	MenuBar, MenuBarItem
	)
{
	return declare(null, {
		is_visible: false,
		name: 'Submenu',
		constructor: function(/* Object */ kwArgs) {
			lang.mixin(this, kwArgs);
			if (!this.store)
				throw "submenu needs a store!";
			if (this.callback === undefined)
				throw "Submenu needs a callback function";
			if (!this.attach_node)
				throw "Submenu needs an attach_node";
		},
		hide: function() {
			if (this.is_visible === true) {
				this.attach_node.removeChild(this.object);
				this.is_visible = false;
				this.callback(false);
			}
		},
		show: function() {
			if (!this.object) {
				this.object = new ContentPane({
					id: 'submenu_' + this.name,
					'class': 'submenu'
				});
				html.set(this.object.get("domNode"), "<div class='submenu_title'>" + this.name + "</div>");
				var dataStore = new ObjectStore({
					objectStore: this.store
				});
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
					onClick: this.on_click_callback
				});
				var menubar = new MenuBar();
				var exp = new MenuBarItem({
					onClick: function() {
						tree.expandAll();
					},
					label: 'Ausklappen'
				});
				var col = new MenuBarItem({
					onClick: function() {
						tree.collapseAll();
					},
					label: 'Einklappen',
					'class': 'right'
				});
				menubar.addChild(exp);
				menubar.addChild(col);
				this.object.addChild(menubar);
				this.object.addChild(tree);
				menubar.startup();
				tree.startup();
			}
			this.attach_node.addChild(this.object);
			this.is_visible = true;
			this.callback(true);
		},
		toggle: function() {
			if (this.is_visible)
				this.hide();
			else
				this.show();
		}
	});
});