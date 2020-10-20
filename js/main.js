const endpoints = "api/endpoints/";




new Vue({
    el: '#sic',
    data: {
        sicVersion: '2.0.4',
        darkMode: false,
        configFileExists: true, // NOTE: we start with 'true' in order to prevent error message to "flicker" on page load
        activeSites: {},
        inactiveSites: {},
        activeSitesSystems: {},
        requestQueue: [],
        requestQueueLength: 0,
        progressMax: 0,
        currentSort:'name',
        currentSortDir:'asc',
        searchTerm: "",
        systemFilter: "",
        isAllSitesRefresh: false,
        summaryUrl: ""
    },
    mounted () {
        this.checkConfigFile();
        this.getActiveSites();
        this.getInactiveSites();
        this.getactiveSitesSystems();
        this.checkSummaryFile();
    },
    computed: {
        progressDone: function(){
            if(this.requestQueueLength>0){
                return this.progressMax - this.requestQueueLength;
            } else {
                return 0;
            }
        },
        /*
        Sort (active) sites based in currentSort and currentSortDir
        Source: https://www.raymondcamden.com/2018/02/08/building-table-sorting-and-pagination-in-vuejs
        */
        sortedSites:function() {
            return Object.values(this.filteredSites).sort((a,b) => {
              let modifier = 1;
              if(this.currentSortDir === 'desc') modifier = -1;
              if(a[this.currentSort] < b[this.currentSort]) return -1 * modifier;
              if(a[this.currentSort] > b[this.currentSort]) return 1 * modifier;
              return 0;
            });
        },
        filteredSites: function(){
            if(this.searchTerm !=""){
                var search = this.searchTerm.toLowerCase();
                var foundsites = {}

                // check if a system filter is set (search "sys:[sytemname]")
                var sysSearch = false;
                var sysToSearch = "";
                var sysAdditional = "";
                var s_splitted = search.split(":",2);
                if(s_splitted.length == 2 && s_splitted[0] === "sys"){
                    // getting searched system (after : but before first space)
                    let sys_after = s_splitted[1].split(" ",2);
                    sysToSearch = sys_after[0];
                    
                    // getting additional search after space (if any)
                    if(sys_after[1]!==undefined){
                        sysAdditional = sys_after[1];
                    }
                    
                    //console.log('System search detected! System: ' + sysToSearch + ' Additional: ' + sysAdditional);
                    // indicate system search
                    sysSearch = true;
                }


                for (let [key, value] of Object.entries(this.activeSites)) {
                    // get hash
                    var id = key;

                    // create one concatenated string for search
                    var concatenatedValues = 
                        value.name.toLowerCase() + '|' + 
                        value.sys.toLowerCase() + '|' + 
                        value.sys_ver.toLowerCase() + '|' + 
                        value.php_ver.toLowerCase() + '|' + 
                        value.sat_ver.toLowerCase() + '|' + 
                        value.date.toLowerCase();

                    var found = false;
                    
                    if(sysSearch){
                        // system search
                        if(value.sys.toLowerCase().indexOf(sysToSearch)!=-1) { 
                            // check if we have additional search 
                            if(sysAdditional !== ""){
                                if(concatenatedValues.indexOf(sysAdditional)!=-1) { found = true };
                            } else {
                                found = true;
                            }
                        };
                    } else {
                        // normal search
                        if(concatenatedValues.indexOf(search)!=-1) { found = true };
                    }
                    
                    // if match was found, add it to foundsites
                    if(found){
                        if(foundsites[id] === undefined){
                            foundsites[id] = value;
                        }
                    }   
                }
                return foundsites;
               
            } else {
                return this.activeSites;
            } 
        }
    },
    watch: {
        // whenever requestQueue changes, this function will run
        requestQueue: function () {
          this.requestQueueLength = this.requestQueue.length;
        }
    },
    methods: {
        objectLength: function(obj) {
            var result = 0;
            for(var prop in obj) {
              if (obj.hasOwnProperty(prop)) {
                result++;
              }
            }
            return result;
        },
        checkConfigFile: function(){
            axios.get(endpoints+'checkConfigFile.php')
            .then(response => { 
                this.configFileExists = response.data
            })
        },
        getActiveSites: function(){
            axios.get(endpoints+'getActiveSites.php')
            .then(response => { 
                var result = response.data;
                // add 'state' property, used for css classes
                for(var k in result) {
                    result[k].state = 'notRefreshed';
                }
                this.activeSites = response.data
            })
        },
        getInactiveSites: function(){
            axios.get(endpoints+'getInactiveSites.php')
            .then(response => { 
                this.inactiveSites = response.data
            })
        },
        getactiveSitesSystems: function(){
            axios.get(endpoints+'getActiveSitesSystems.php')
            .then(response => { 
                this.activeSitesSystems = response.data
            })
        },
        refreshSingleSite: function(event){
            var id = event.currentTarget.getAttribute('data-id');
            this.requestQueue.push(id);
            this.doRefresh(id);
        },
        refreshAllSites: function(){
            //add all active sites to queue
            for(var id in this.activeSites){
                this.requestQueue.push(id);
            };
            this.isAllSitesRefresh = true;
            this.doRefresh();

        },
        refreshFilteredSites: function(){
            //add all filtered sites to queue
            for(var id in this.filteredSites){
                this.requestQueue.push(id);
            };
            this.doRefresh();
        },
        doRefresh: function(){

            var RequestPromises = []

            this.progressMax = this.requestQueue.length;
            
            // loop through requestQueue
            for (let [key, value] of Object.entries(this.requestQueue)) {
                //console.log(`${key}: ${value}`);

                // get hash
                var id = value;

                // get name
                var name = this.activeSites[id].name;

                // change state (used for coloring active row)
                this.activeSites[id].state = 'refreshing';

                // request to endpoint
                var SinglePromise = axios.post(endpoints+'getSatelliteResponse.php', {
                    hash: id
                })
                .then(response => { 

                    // getting id (hash) and name from sat
                    // NOTE: we cannot use var 'id' here, because of promise
                    var hash = response.data.hash;
                        sitename = response.data.name;

                    if(response.data.statuscode==200){
                        // getting satellite data
                        var satdata = response.data.response;
                        satdata = JSON.parse(satdata);

                        // getting time and date
                        var time = response.data.time;
                        var date = response.data.date;  
        
                        // getting URL to history CSV file
                        var history = response.data.history;
                        
                        // update data
                        this.activeSites[hash].sys_ver = satdata.sys_ver;
                        this.activeSites[hash].php_ver = satdata.php_ver;
                        this.activeSites[hash].sat_ver = satdata.sat_ver;
                        this.activeSites[hash].date = date;
                        this.activeSites[hash].time = time;
                        this.activeSites[hash].history = history;
                        
                        // remove '.refreshing' class from row
                        this.activeSites[hash].state = "";
                    } else {
                        this.activeSites[hash].state = "refresh-error";
                        this.notify('danger','<strong>'+this.activeSites[hash].name+': </strong>Refresh failed<br/><small>' + response.data.message +'</small>');
                    }   
                    
                    
                    // remove one element from the beginning of the array
                    // (=latest processed element)
                    this.requestQueue.shift();
                    console.log(hash + ' (' + sitename + ') processed, '+this.requestQueue.length + ' more items to process ...');

                })
                .catch(function (error) {
                    // handle error
                    console.log(error);
                })
                .then(function () {
                    // always executed
                });
                

                // push SinglePromise to array RequestPromises
                RequestPromises.push(SinglePromise);
               

            } // end for() llop

            /*
            execute if all requests (promises) completet (with or without error)
            see promiseReflect() method for info why .map() is invoked
            */
            Promise.all(RequestPromises.map(this.promiseReflect)).then(response => {
                console.log('Queue completed.');
                this.notify('success','<strong>Refresh queue completed</strong>');

                if(this.isAllSitesRefresh === true){
                    this.writeSummary();
                }
            });
            
            
        },
        /* 
        A helper method used in doRefresh() Promis.all in order to execute Promis.all
        even if there was an error during satellite fetching.
        Source: https://stackoverflow.com/questions/31424561/wait-until-all-es6-promises-complete-even-rejected-promises/31424853#31424853
        */
        promiseReflect: function(promise) {
            return promise.then(function (v) { return { v: v, status: "resolved" } },
                function (e) { return { e: e, status: "rejected" } });
        },
        notify: function(status='success', message='message') {
            UIkit.notification({
                message: message,
                status: status,
                pos: 'top-right',
                timeout: 5000
            });
        },
        /*
        Method for setting currentSort and currentSortDir
        Source: https://www.raymondcamden.com/2018/02/08/building-table-sorting-and-pagination-in-vuejs
        */
        sort: function(s) {
            // if s == current sort, reverse
            if(s === this.currentSort) {
              this.currentSortDir = this.currentSortDir==='asc'?'desc':'asc';
            } else {
                this.currentSortDir = 'asc';
            }
            this.currentSort = s;
        },
        sysFilterChange: function(event){
            if(event.target.value !== ""){
                this.searchTerm = "sys:"+event.target.value+" "; // white space at the end, so user can add addtional search string
            } else {
                this.searchTerm = "";
            }
        },
        resetSearchAndFilter: function(){
            this.searchTerm = "";
            this.systemFilter = "";
        },
        writeSummary: function(){
            if(this.isAllSitesRefresh === true){
                axios.get(endpoints+'writeSummaryAndGetUrl.php')
                .then(response => { 
                    if(response.data != false || response.data !=""){
                        this.summaryUrl = response.data;
                        // notiy that summary is available
                        UIkit.notification({
                            message: '<div style="text-align:center"><h2>Summary created</h2><a class="uk-button uk-button-danger" href="'+this.summaryUrl+'" target="_blank">Dowload</a></div>',
                            status: 'primary',
                            pos: 'bottom-right',
                            timeout: 15000
                        }); 
                    } else {
                        this.summaryUrl = "";
                    }
                })
            }
            this.isAllSitesRefresh = false;
        },
        checkSummaryFile: function(){
            axios.get(endpoints+'checkSummaryFileAndGetUrl.php')
            .then(response => { 
                if(response.data != false || response.data !=""){
                    this.summaryUrl = response.data; 
                } else {
                    this.summaryUrl = "";
                }
            })
        },
        toggleDarkMode: function(){
            // switch between true and false
            this.darkMode ^= true;

            // store state in window object in order to access it in iframe (historyviewer)
            window.darkMode = this.darkMode;

            // get root <html> element
            var root = document.documentElement;

            // add class to <html> element based on state
            // (via DOM manipulation, as element out of vue scope)
            if(this.darkMode){
                root.classList.add('darkmode');
            } else {
                root.classList.remove('darkmode');
            }
        }
        
    }
}); 