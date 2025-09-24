$(function () { // shorthand for document ready
  // Pickup autocomplete
  $("#pickup").autocomplete({
    minLength: 2,
    source: function (request, response) {
      $.getJSON("https://photon.komoot.io/api/", {
        q: request.term,
        limit: 5,
        lang: "en",
        bbox: "116,4.5,127,21.5" // Philippines bounding box
      }).done(function (data) {
        response($.map(data.features, function (f) {
          return {
            label: f.properties.name + ", " + (f.properties.city || "") + ", " + (f.properties.country || ""),
            value: f.properties.name,
            lat: f.geometry.coordinates[1],
            lng: f.geometry.coordinates[0]
          };
        }));
      });
    },
    select: function (event, ui) {
      $("#pickup_lat").val(ui.item.lat);
      $("#pickup_lng").val(ui.item.lng);
    }
  });

  // Destination autocomplete
  $("#dropoff").autocomplete({
    minLength: 2,
    source: function (request, response) {
      $.getJSON("https://photon.komoot.io/api/", {
        q: request.term,
        limit: 5,
        lang: "en",
        bbox: "116,4.5,127,21.5" // Philippines bounding box
      }).done(function (data) {
        response($.map(data.features, function (f) {
          return {
            label: f.properties.name + ", " + (f.properties.city || "") + ", " + (f.properties.country || ""),
            value: f.properties.name,
            lat: f.geometry.coordinates[1],
            lng: f.geometry.coordinates[0]
          };
        }));
      });
    },
    select: function (event, ui) {
      $("#dropoff_lat").val(ui.item.lat);
      $("#dropoff_lng").val(ui.item.lng);
    }
  });
});
