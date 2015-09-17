var profile = {
	resourceTags: {
		test: function(filename, mid){
			return false;
		},

		miniExclude: function(filename, mid){
			return false;
		},

		amd: function(filename, mid){
			return /\.js$/.test(filename);
		}
	}
};