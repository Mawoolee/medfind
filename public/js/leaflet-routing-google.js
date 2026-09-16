// ============================================
// Google Maps Directions API Router for Leaflet
// ============================================

// Custom Google Maps Router for Leaflet Routing Machine
L.Routing.Google = L.Class.extend({
    options: {
        serviceUrl: 'https://maps.googleapis.com/maps/api/directions/json',
        travelMode: 'DRIVING', // DRIVING, WALKING, BICYCLING, TRANSIT
        apiKey: null
    },

    initialize: function(options) {
        L.Util.setOptions(this, options);
        if (!this.options.apiKey) {
            console.error('Google Maps API key is required');
        }
    },

    route: function(waypoints, callback, context, options) {
        const that = this;
        const url = this._buildUrl(waypoints);

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'OK' && data.routes && data.routes.length > 0) {
                    const routes = that._convertRoutes(data.routes);
                    callback.call(context, null, routes);
                } else {
                    callback.call(context, {
                        status: data.status,
                        message: data.error_message || 'No routes found'
                    });
                }
            })
            .catch(error => {
                callback.call(context, {
                    status: -1,
                    message: 'Failed to get directions: ' + error.message
                });
            });
    },

    _buildUrl: function(waypoints) {
        const origin = waypoints[0].latLng.lat + ',' + waypoints[0].latLng.lng;
        const destination = waypoints[waypoints.length - 1].latLng.lat + ',' + waypoints[waypoints.length - 1].latLng.lng;
        
        let url = 'https://maps.googleapis.com/maps/api/directions/json' +
            '?origin=' + encodeURIComponent(origin) +
            '&destination=' + encodeURIComponent(destination) +
            '&mode=' + this.options.travelMode.toLowerCase() +
            '&alternatives=true' +
            '&key=' + this.options.apiKey;

        // Add waypoints if more than 2
        if (waypoints.length > 2) {
            const via = waypoints.slice(1, -1)
                .map(wp => wp.latLng.lat + ',' + wp.latLng.lng)
                .join('|');
            url += '&waypoints=' + encodeURIComponent(via);
        }

        return url;
    },

    _convertRoutes: function(googleRoutes) {
        const that = this;
        return googleRoutes.map(function(route) {
            return {
                name: route.summary,
                coordinates: that._decodePolyline(route.overview_polyline.points),
                instructions: that._convertInstructions(route.legs[0]),
                summary: {
                    totalDistance: route.legs[0].distance.value,
                    totalTime: route.legs[0].duration.value
                },
                inputWaypoints: [],
                waypoints: [],
                properties: {}
            };
        });
    },

    _decodePolyline: function(encoded) {
        // Decode Google polyline encoding
        const poly = [];
        let index = 0, len = encoded.length;
        let lat = 0, lng = 0;

        while (index < len) {
            let b, shift = 0, result = 0;
            do {
                b = encoded.charCodeAt(index++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while (b >= 0x20);
            const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1));
            lat += dlat;

            shift = 0;
            result = 0;
            do {
                b = encoded.charCodeAt(index++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while (b >= 0x20);
            const dlng = ((result & 1) ? ~(result >> 1) : (result >> 1));
            lng += dlng;

            poly.push(L.latLng(lat / 1e5, lng / 1e5));
        }
        return poly;
    },

    _convertInstructions: function(leg) {
        return leg.steps.map(function(step, index) {
            return {
                type: that._getInstructionType(step.maneuver),
                text: step.html_instructions.replace(/<[^>]*>/g, ''), // Strip HTML
                distance: step.distance.value,
                time: step.duration.value,
                index: index,
                exit: step.exit_number
            };
        });
    },

    _getInstructionType: function(maneuver) {
        const map = {
            'turn-left': 'Left',
            'turn-right': 'Right',
            'turn-slight-left': 'SlightLeft',
            'turn-slight-right': 'SlightRight',
            'turn-sharp-left': 'SharpLeft',
            'turn-sharp-right': 'SharpRight',
            'uturn-left': 'UTurn',
            'uturn-right': 'UTurn',
            'ramp-left': 'Ramp',
            'ramp-right': 'Ramp',
            'merge': 'Merge',
            'fork-left': 'Fork',
            'fork-right': 'Fork',
            'ferry': 'Ferry',
            'roundabout-left': 'Roundabout',
            'roundabout-right': 'Roundabout'
        };
        return map[maneuver] || 'Straight';
    }
});

L.Routing.google = function(options) {
    return new L.Routing.Google(options);
};
