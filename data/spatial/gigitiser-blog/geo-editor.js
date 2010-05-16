if (! window.HEURIST) {
	HEURIST = {};
}

HEURIST.GeoEditor = function (mapElemID) {

	var that = {

	map: null,
	digitiser: null,
	record: null,
	onsaveCallback: null,
	geoDetailType: HDetailManager.getDetailTypeById(230),

	init: function () {
		var matches, id;
		if (! HCurrentUser.isLoggedIn()) {
				alert('You are not currently logged in to Heurist');
				location.replace('../php/login.php');
				return;
		}

		if (GBrowserIsCompatible()) {
			this.map = new GMap2(document.getElementById(mapElemID));
			this.map.enableDoubleClickZoom();
			this.map.enableContinuousZoom();
			this.map.enableScrollWheelZoom();
			this.map.addControl(new GLargeMapControl());
			this.map.addControl(new GMapTypeControl());
			this.map.removeMapType(G_SATELLITE_MAP);
			this.map.addMapType(G_PHYSICAL_MAP);
			this.map.setCenter(new GLatLng(4, 100), 2, G_PHYSICAL_MAP);
		}

		this.digitiser = new HAPI.GOI.Digitiser(this.map);

		matches = location.search.match(/\bd=([0-9]+)/);
		if (matches && matches[1]) {
			this.geoDetailType = HDetailManager.getDetailTypeById(matches[1]);
		}

		matches = location.search.match(/id=([0-9]+)/);
		if (matches && matches[1]) {
			id = matches[1];
			HeuristScholarDB.loadRecords(
				new HSearch('id:' + id),
				new HLoader(
					function (s,r,c) {
						that.record = r[0];
						that.renderGeos();
					},
					function (s, e) {
						alert("load failed: " + e);
					}
				)
			);
		}

		matches = location.search.match(/cb=([^&]+)/);
		if (matches && matches[1]) {
			this.onsaveCallback = matches[1];
		}

	},

	renderGeos: function () {
		var geos, geo, l, i;
		$("#geos").empty();
		geos = this.record.getDetails(this.geoDetailType);
		if (geos && geos.length) {
			$("#geos").append("<p>Geographic objects (click to edit):</p>");
			l = geos.length;
			for (i = 0; i < l; i++) {
				(function (geo) {
					$p = $("<p>").appendTo("#geos");
					$("<a href='#'>" + geo.toString() + "</a>")
						.attr("title", "click to edit")
						.click(function () {
							$("#select-type, #msg, #geos, #add-geo, #save-record").hide();
							$("#save-geo").show();
							$("#save-geo-button").click(function () {
								that.replaceGeo(geo, that.digitiser.getShape());
								that.digitiser.getMap().clearOverlays();
								$("#save-geo").hide();
								$("#geos, #save-record").show();
								that.conditionalShowAddGeo();
								$("#save-geo-button").unbind("click");
							});
							$("#cancel-geo-button").click(function () {
								that.digitiser.getMap().clearOverlays();
								$("#save-geo").hide();
								$("#geos, #save-record").show();
								that.conditionalShowAddGeo();
								$("#cancel-geo-button").unbind("click");
							});
							that.digitiser.edit(geo);
							return false;
						})
						.appendTo($p);

					$p.append(" ");

					$("<a href='#'><img src='../img/cross.gif'/></a>")
						.attr("title", "click to remove this object")
						.click(function () {
							that.replaceGeo(geo, null);
							return false;
						})
						.appendTo($p);
				})(geos[i]);
			}
		}
		that.conditionalShowAddGeo();
	},

	conditionalShowAddGeo: function () {
		if (HDetailManager.getDetailRepeatable(this.record.getRecordType(), this.geoDetailType)) {
			$("#add-geo").show();
		} else if (this.record.getDetails(this.geoDetailType).length === 0) {
			$("#add-geo").show();
		}
	},

	addGeo: function () {
		$("#add-geo, #msg, #geos, #save-record").hide();
		$("#select-type").show();
		$("#save-geo-button").click(function () {
			that.addNewGeo(that.digitiser.getShape());
			that.digitiser.getMap().clearOverlays();
			$("#save-geo, #select-type").hide();
			$("#geos, #save-record").show();
			that.conditionalShowAddGeo();
			$("#save-geo-button").unbind("click");
		});
		$("#cancel-geo-button").click(function () {
			that.digitiser.getMap().clearOverlays();
			$("#save-geo, #select-type").hide();
			$("#geos, #save-record").show();
			that.conditionalShowAddGeo();
			$("#cancel-geo-button").unbind("click");
		});
	},

	selectButton: function (x) {
		for (var i = 0; i < 5; ++i) {
			var e = document.getElementById('button'+i);
			if (e === x)
				e.style.fontWeight = 'bold';
			else
				e.style.fontWeight = '';
		}
		$("#msg").val(
			x ? "Double-click the map to add a " + x.value + " object"
			: "").show();
		if (x) {
			$("#save-geo").hide();
		} else {
			$("#save-geo").show();
		}
	},

	addNewGeo: function (newGeo) {
		this.record.addDetail(this.geoDetailType, newGeo);
		this.renderGeos();
	},

	replaceGeo: function (oldGeo, newGeo) {
		var geos, l, i;
		if (HDetailManager.getDetailRepeatable(this.record.getRecordType(), this.geoDetailType)) {
			geos = this.record.getDetails(this.geoDetailType);
			l = geos.length;
			for (i = 0; i < l; i++) {
				if (geos[i] === oldGeo) {
					if (newGeo) {
						geos[i] = newGeo;
					} else {
						geos.splice(i, 1);
						break;
					}
				}
			}
		} else {
			geos = newGeo ? [ newGeo ] : [];
		}
		this.record.setDetails(this.geoDetailType, geos);
		this.renderGeos();
	},

	save: function () {
		var saver = new HSaver(
			function(_) {
				if (window.opener) {
					if (that.onsaveCallback  &&  window.opener[that.onsaveCallback]) {
						window.opener[that.onsaveCallback]();
					}
					setTimeout(window.close, 10);
				}
			},
			function(_,e) {
				alert("Record save failed: " + e);
			}
		);
		HeuristScholarDB.saveRecord(this.record, saver);
	}

	};

	return that;
}

