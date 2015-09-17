// On demand common data
function Warehouse() {}
Warehouse.prototype.store = [];
Warehouse.prototype.get = function(name, is_store) {
	if (!name)
		throw "No name given";
	if (name in this.store)
		return this.store[name];
	if (is_store) {
		name = name.toLowerCase();
		this.set(name, this._get_store(name.charAt(0).toUpperCase() + name.substr(1)));
		return this.store[name];
	}
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
	require(['dojo/store/JsonRest', 'partdb/Cache', 'dojo/store/Memory'],
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
	return this._devices;
}
Warehouse.prototype.get_categories = function() {
	if (!this._categories)
		this._categories = this._get_store('Categories');
	return this._categories;
} 
