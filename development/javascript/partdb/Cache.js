define(["dojo/_base/lang","dojo/when"],
	   function(lang, when) {
		   
		   // module:
		   //		dojo/store/Cache
		   
		   var Cache = function(masterStore, cachingStore, options){
			   options = options || {};
			   return lang.delegate(masterStore, {
				   query: function(query, directives){
					   var results = masterStore.query(query, directives);
					   results.forEach(function(object){
						   if(!options.isLoaded || options.isLoaded(object)){
							   cachingStore.put(object);
						   }
					   });
					   return results;
				   },
				   // look for a queryEngine in either store
				   queryEngine: masterStore.queryEngine || cachingStore.queryEngine,
				   get: function(id, directives){
					   return when(cachingStore.get(id), function(result){
						   return result || when(masterStore.get(id, directives), function(result){
							   if(result){
								   cachingStore.put(result, {id: id});
							   }
							   return result;
						   });
					   });
				   },
				   add: function(object, directives){
					   return when(masterStore.add(object, directives), function(result){
						   // now put result in cache
						   cachingStore.add(result && typeof result == "object" ? result : object, directives);
						   return result; // the result from the add should be dictated by the masterStore and be unaffected by the cachingStore
					   });
				   },
				   put: function(object, directives){
					   // first remove from the cache, so it is empty until we get a response from the master store
					   cachingStore.remove((directives && directives.id) || this.getIdentity(object));
					   // If before is set, modify parent (before = parent id)
					   if (directives && directives.before !== undefined)
						   object.parent = directives.before && directives.before.id ? directives.before.id : 0;
					   return when(masterStore.put(object, directives), function(result){
						   // now put result in cache
						   cachingStore.put(result && typeof result == "object" ? result : object, directives);
						   return result; // the result from the put should be dictated by the masterStore and be unaffected by the cachingStore
					   });
				   },
				   remove: function(id, directives){
					   return when(masterStore.remove(id, directives), function(result){
						   return cachingStore.remove(id, directives);
					   });
				   },
				   evict: function(id){
					   return cachingStore.remove(id);
				   }
			   });
		   };
		   lang.setObject("partdb.Cache", Cache);   
		   return Cache;
	   }); 
