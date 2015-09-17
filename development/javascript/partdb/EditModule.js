define([
'dojo/_base/declare', "dojo/_base/lang",
'dijit/form/Button', 'dijit/Dialog',
'dijit/form/CheckBox', 'dijit/form/TextBox',
'dgrid/OnDemandGrid', 'dgrid/extensions/DnD',
'dgrid/Keyboard', 'dgrid/Selection',
'dgrid/editor', 'dgrid/selector', 'dgrid/tree'
], function(declare, lang, Button, Dialog,
			CheckBox, TextBox, OnDemandGrid,
			DnD, Keyboard, Selection, editor,
			selector, tree)
{
	return declare(null, {
		//item_template: {},
		//dialog: {},
		//store
		//item_template
		buttons: [],
		dialog: new Dialog({
			title: "Achtung",
			style: "width: 400px"
		}),
		constructor: function(/* Object */ kwArgs) {
			lang.mixin(this, kwArgs);
			if (!this.store)
				throw "EditModule needs a store."
			if (!this.item_template)
				throw "EditModule needs item_template to be defined."
			if (!this.attach_node)
				throw "EditModule needs a attach_node."
		},
		show: function(){
			if (this.grid) {
				this.attach_node.addChild(this.grid);
				this.grid.startup();
				console.log(this.buttons);
				for (var i in this.buttons) {
					var button = this.buttons[i];
					console.log(button);
					this.attach_node.addChild(button);
					button.startup();
				}
			} else {
				this._create_grid();
			}
		},
		_save: function() {
			if (!this.grid.selection)
				return;
			var selected = this.grid.selection;
			var items = [];
			var content = '<p>Folgende Speichern?</p><ul>';
			for (var id in selected) {
				if (!this.grid.dirty[id])
					continue;
				var new_item = this.grid.dirty[id];
				var item = this.store.get(id);
				for (f in item) {
					if (f.substring(0, 1) !== '_' && !new_item[f])
						new_item[f] = item[f];
				}
				items.push(new_item);
				content += '<li>' + new_item.name + '</li>';
			}
			content += '</ul><br />';
			this.dialog.set('content', content);
			console.log(items);
			var ok = new Button({
				label: 'Ok',
				onClick: dojo.hitch(this, function() {
					for(var i = 0, len = items.length; i < len; ++i)
						this.store.put(items[i], {overwrite: true}).then( dojo.hitch(this, function(){
							// Ok 
							this.dialog.hide();
							this.grid.refresh({keepScrollPosition: true});
						}),
						dojo.hitch(this, function(response){
							//ERROR
							console.log(response.status);
							this.dialog.set('content', "Error: " + response.status);
						}));
					this.dialog.destroyDescendants();
				})
			});
			var cancel = new Button({
				label: 'Abbrechen',
				onClick: dojo.hitch(this, function() {
					this.dialog.hide();
					this.dialog.destroyDescendants();
				})
			});
			this.dialog.addChild(ok);
			this.dialog.addChild(cancel);
			ok.startup();
			cancel.startup();
			this.dialog.show();
		},
		_remove: function() {
			var selected = this.grid.selection;
			if (!selected)
				return;
			var content = '<p>Folgende Entfernen?</p><ul>';
			for (var item in selected) {
				content += '<li>' + this.store.get(item).name + '</li>';
			}
			content += '</ul><br />';
			this.dialog.set('content', content);
			var ok = new Button({
				label: 'Ok',
				onClick: dojo.hitch(this, function() {
					for (var item in selected)
						this.store.remove(item).then( dojo.hitch(this, function(){
							// Ok 
							this.dialog.hide();
							this.grid.refresh({keepScrollPosition: true});
						}),
						dojo.hitch(this, function(response){
							//ERROR
							console.log(response.status);
							this.dialog.set('content', "Error: " + response.status);
						}));
					this.dialog.destroyDescendants();
				})
			});
			var cancel = new Button({
				label: 'Abbrechen',
				onClick: dojo.hitch(this, function() {
					this.dialog.hide();
					this.dialog.destroyDescendants();
				})
			});
			this.dialog.addChild(ok);
			this.dialog.addChild(cancel);
			ok.startup();
			cancel.startup();
			this.dialog.show();
		},
		_create: function() {
				var parent = null;
				for (parent in this.grid.selection) break;
				var content = '<div>';
				for ( i in this.item_template) {
					var item = this.item_template[i];
					content += '<label for="create_' + item.name +
					'">' + item.label + '</label><div id="create_'
					+ item.name + '"></div><br />';
				}
				content += '</div>';
				this.dialog.set('content', content);
				var widgets = this._create_widgets();
				var ok = new Button({
					label: 'Ok',
					onClick: dojo.hitch(this, function() {
						var item = {parent: parent};
						for (i in widgets) {
							item[widgets[i].name] = widgets[i].get_value();
						}
						this.store.add(item).then(
							function(){
								// Ok
								console.log("CREATE OK");
								this.dialog.hide();
								this.grid.refresh({keepScrollPosition: true});
							},
						   function(response){
							   //ERROR
							   console.log(response.status);
							   this.dialog.set('content', "Error: " + response.status);
						   }
						);
						this.grid.refresh({keepScrollPosition: true});
						this.dialog.destroyDescendants();
					})
				});
				var cancel = new Button({
					label: 'Abbrechen',
					onClick: dojo.hitch(this, function() {
						this.dialog.hide();
						this.dialog.destroyDescendants();
					})
				});
				this.dialog.addChild(ok);
				this.dialog.addChild(cancel);
				this.dialog.show();
		},
		_create_widgets: function() {
			var widgets = [];
			for (var i in this.item_template) {
				var item = this.item_template[i];
				var widget;
				if (item.type === 'bool')
					widget = new CheckBox({
						name: item.name,
						get_value: function() {
							return this.checked;
						}
					}, 'create_' + item.name);
				else if (item.type === 'text')
					widget = new TextBox({
						name: item.name,
						get_value: function() {
							return this.value;
						}
					}, 'create_' + item.name);
				widget.startup();
				widgets.push(widget);
			}
			return widgets;
		},
		_create_colums: function() {
			this.columns = [ selector({ className: "selector" }) ];
			for (var i in this.item_template) {
				var item = this.item_template[i];
				if (item.name === 'name') {
					this.columns.push(
						tree(
							editor({
								label: item.label,
								field: "name",
								sortable: true,
								editor: "text",
								editOn: "dblclick"
							})
						)
					);
				} else {
					this.columns.push(
						editor({
							label: item.label,
							field: item.name,
							sortable: false,
							editor: item.type === 'bool' ? 'checkbox' : 'text'
						})
					);
				}
			}
			return this.columns;
		},
		_create_grid: function() {
			this.grid = new (declare([ OnDemandGrid, Keyboard, DnD, Selection]))({
				store: this.store,
				query: {parent: 0},
				loadingMessage: "Loading data...",
				allowSelectAll: true,
				columns: this._create_colums()
			});
			this.buttons.push(new Button({
				label: 'Ausgewählte löschen',
				onClick: dojo.hitch(this, this._remove)
			}));
			this.buttons.push(new Button({
				label: 'Ausgewählte speichern',
				onClick: dojo.hitch(this, this._save)
			}));
			this.buttons.push(new Button({
				label: 'Alle zurücksetzen',
				onClick: dojo.hitch(this, function() {
					this.grid.revert();
				})
			}));
			this.buttons.push(new Button({
				label: 'Neu erstellen',
				onClick: dojo.hitch(this, this._create)
			}));
			this.attach_node.addChild(this.grid);
			for (var i in this.buttons) {
				var button = this.buttons[i];
				this.attach_node.addChild(button);
				button.startup();
			}
			this.grid.startup();
		}
	});
});