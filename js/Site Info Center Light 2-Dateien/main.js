const endpoints = "api/endpoints/";




new Vue({
    el: '#sic',
    data: {
        activeSites: null,
        inactiveSites: null
    },
    computed:{
        
    },
    mounted () {
        this.getActiveSites();
        this.getInactiveSites();
        
    },
    methods: {
        objectLength: function(obj) {
            var result = 0;
            for(var prop in obj) {
              if (obj.hasOwnProperty(prop)) {
              // or Object.prototype.hasOwnProperty.call(obj, prop)
                result++;
              }
            }
            return result;
        },
        getActiveSites: function(){
            axios.get(endpoints+'getActiveSites.php')
            .then(response => { 
                this.activeSites = response.data
            })
        },
        getInactiveSites: function(){
            axios.get(endpoints+'getInactiveSites.php')
            .then(response => { 
                this.inactiveSites = response.data
            })
        },
        refreshSingleSite: function(event){
            var id = event.currentTarget.getAttribute('data-id');
            var name = event.currentTarget.getAttribute('data-name');
            console.log('Refresh Site with ID: ' + id + ' (' + name + ')');
            axios.post(endpoints+'getSatelliteResponse.php', {
                hash: id
            })
            .then(response => { 
                console.log(response.data);
                var satdata = JSON.stringify(response.data);
                console.log(satdata); // TO: Create access to the returned values
                console.log(this.activeSites[id]);
                
            })
        }
    }
}); 