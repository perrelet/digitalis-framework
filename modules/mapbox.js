export class Digitalis_Mapbox {

    static default_options = {
        style:      'mapbox://styles/mapbox/outdoors-v12',
        projection: 'globe',
        latitude:   51.6967,
        longitude:  -0.9189,
        zoom:       10.8,
        bearing:    0,
        pitch:      0,
        location:   true,
    };

    static default_state = {
        location: {
            selected: false,
            debounce: false,
        },
    };

    map     = null;
    date    = {};
    options = {};
    state   = {};

    constructor (data = {}, options = {}) {

        this.data    = data;
        this.options = Object.assign({}, Digitalis_Mapbox.default_options, options || {});
        this.state   = Digitalis_Mapbox.default_state;
        
        this.add_event_listeners();

    }

    on_map_load () {

        if (this.options.location) this.add_location_pin();

    }

    add_event_listeners () {

        if (this.options.location) document.addEventListener('Eventropy/Location/Updated', (e) => {

            this.place_location_pin(e.detail.lng, e.detail.lat);
            //this.calc_bounds();

            setTimeout(() => this.calc_bounds(), 1000); // We delay to ensure the coords have been updated - https://github.com/mapbox/mapbox-gl-js/issues/4904

        });

        document.addEventListener('Digitalis/Query/Request_Items', (e) => {

            if (this.popup) this.popup.remove();

        });

    }

    init_map () {

        mapboxgl.accessToken = this.options.token;

        this.map = new mapboxgl.Map({
            container:  'mapbox',
            projection: this.options.projection,
            style:      this.options.style,
            center:     [this.options.longitude, this.options.latitude],
            zoom:       this.options.zoom,
            bearing:    this.options.bearing,
            pitch:      this.options.pitch,
        });

        this.map.on('load', () => this.on_map_load());

    }

    add_location_pin () {

        if (!this.data.hasOwnProperty('location') || !this.data.location) return;

        this.add_location_source();
        this.add_location_layer();
        this.add_location_event_listeners();

    }

    add_location_layer () {

        this.map.addLayer({
            id: 'location-node',
            type: 'circle',
            source: 'location',
            filter: ['!', ['has', 'point_count']],
            paint: {
                'circle-color': '#11b4da',
                'circle-radius': 8,
                'circle-stroke-width': 1,
                'circle-stroke-color': [
                    'case',
                    ['==', ['feature-state', 'selected'], true], '#ffffff',
                    ['==', ['feature-state', 'hover'], 1], '#0c3f91',
                    '#4286f5'
                ],
            }
        });

    }

    add_location_source () {

        this.map.addSource('location', {
            type: 'geojson',
            data: this.data.location,
        });

    }

    add_location_event_listeners () {

        this.map.on('mouseenter', 'location-node', (e) => {

            this.map.setFeatureState({
                source: 'location',
                id: 0,
            },{
                hover: 1
            });

        });

        this.map.on('mouseleave', 'location-node', (e) => {

            this.map.setFeatureState({
                source: 'location',
                id: 0,
            },{
                hover: 0
            });

        });

        this.map.on('click', 'location-node', (e) => {

            if (!this.select_location_pin(!this.state.location.selected)) return;

            if (!this.state.location.selected) {

                this.set_location(e.lngLat.lng, e.lngLat.lat);
                this.calc_bounds();

                document.dispatchEvent(new CustomEvent('Digitalis/Query/Control/Submit', {detail: {}}));

            }

        });

        /* this.map.on('click', (e) => {

            if (!this.state.location.selected)    return;
            if (!this.select_location_pin(false)) return;

            this.set_location(e.lngLat.lng, e.lngLat.lat);

        }); */

        this.map.on('mousemove', (e) => {
                        
            if (!this.state.location.selected) return;

            this.place_location_pin(e.lngLat.lng, e.lngLat.lat);

        });

    }

    place_location_pin (lng, lat) {

        if (!this.map.getSource('location')) {

            setTimeout(() => this.place_location_pin(lng, lat), 250);
            return;

        }

        this.data.location.features[0].geometry.coordinates[0] = lng;
        this.data.location.features[0].geometry.coordinates[1] = lat;
        this.map.getSource('location').setData(this.data.location);   //

    }

    select_location_pin (selected = true) {

        if (this.state.location.debounce) return false;

        this.state.location.selected = selected;

        this.map.setFeatureState({
            source: 'location',
            id:     0,
        },{
            selected: selected,
        });

        this.state.location.debounce = true;
        setTimeout(() => { this.state.location.debounce = false; }, 100);

        return true;
        
    }

    set_location (longitude, latitude) {

        this.place_location_pin(longitude, latitude);
        eventropy_location.set_location(latitude, longitude, 'input', false);
        this.calc_bounds();

    }

    //

    select_cluster (point, cluster_layer, source) {

        const features = this.map.queryRenderedFeatures(point, {
            layers: [cluster_layer]
        });

        const clusterId = features[0].properties.cluster_id;

        this.map.getSource(source).getClusterExpansionZoom(
            clusterId,
            (err, zoom) => {

                if (err) return;
                
                this.map.easeTo({
                    center: features[0].geometry.coordinates,
                    zoom: zoom
                });
            }
        );

    }

    set_state (sources, state = {}, filter = false, data = {}) {

        if (!(sources instanceof Array)) sources = [sources];

        sources.forEach(source => {

            this.data[source].features.forEach(feature => {

                if (filter && !filter(feature, data)) return;

                this.map.setFeatureState({
                    source: source,
                    id: feature.id,
                }, state);

            });

        });

    }

    set_data (data) {

        for (const [source_id, collection] of Object.entries(data)) {

            this.set_source_data(source_id, collection);
            
        }

    }

    set_source_data (source_id, collection) {

        const source = this.map.getSource(source_id);

        if (source) {

            this.data[source_id] = collection;
            source.setData(collection);

        }

    }

    calc_bounds () {

        let bounds = [
            [Number.POSITIVE_INFINITY, Number.POSITIVE_INFINITY],
            [Number.NEGATIVE_INFINITY, Number.NEGATIVE_INFINITY],
        ];

        let n = 0;

        Object.keys(this.data).forEach(source_id => {

            //if (source_id == 'location') return;

            let features = this.data[source_id].features;

            if (features) features.forEach(feature => {

                if (feature.geometry.type != 'Point') return;

                let long = feature.geometry.coordinates[0];
                let lat  = feature.geometry.coordinates[1];

                if ((long < -180) || (long > 180)) return;
                if ((lat < -90) || (lat > 90))     return;

                n++;

                bounds = [
                    [Math.min(bounds[0][0], long), Math.min(bounds[0][1], lat)],
                    [Math.max(bounds[1][0], long), Math.max(bounds[1][1], lat)],
                ];

            });

        });

        if (n > 1) this.set_bounds(bounds);

    }

    set_bounds (bounds) {

        const filters_panel = document.getElementById('filters');
        const width  = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        let padding = Math.min(100, width * 0.05);

        if (filters_panel && (width > 1000) && filters_panel.classList.contains('open')) {

            padding = {
                top:    padding,
                bottom: padding,
                left:   padding + filters_panel.getBoundingClientRect().right,
                right:  padding,
            };

        }

        this.map.fitBounds(bounds, {
            padding: padding,
        });

    }

}