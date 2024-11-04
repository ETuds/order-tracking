 <?php
//Create a Shorcode for the maps in the Track my order page
	function track_order_map_function($atts){
		
		$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$uri_segments = explode('/', $uri_path);
		$orderID = $uri_segments[2];

		if (isset($atts['order-id'])) {

			$orderID = $atts['order-id'];
		}

		$order = new WC_Order($orderID);
		$order_meta = get_post_meta($order->ID);
		$shipping_coordinates = unserialize($order_meta['_eclectus_order_shipping_coordinates'][0]);
		$shipping_coordinates_lat = $shipping_coordinates['lat'];
		$shipping_coordinates_lng = $shipping_coordinates['long'];		

		foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
			$shipping_method_title = $shipping_item_obj->get_method_title();
		}

		get_status_progress($shipping_method_title,$order->ID);

		$pb = array();


		// $hide_elemets = $order->get_status() != 'shipping' || $order->get_status() != 'toship' ? 'hide-element': '';
		$status_accept = array('shipping');

		
		$eti_firebase_api_key = '"'.eti_get_settings("eti_firebase_api_key").'"';
		$eti_firebase_auth_domain = '"'.eti_get_settings("eti_firebase_auth_domain").'"';
		$eti_firebase_database_url = '"'.eti_get_settings("eti_firebase_database_url").'"';
		$eti_firebase_project_id = '"'.eti_get_settings("eti_firebase_project_id").'"';
		$eti_firebase_storage_bucket = '"'.eti_get_settings("eti_firebase_storage_bucket").'"';
		$eti_firebase_messaging_sender_id = '"'.eti_get_settings("eti_firebase_messaging_sender_id").'"';
		$eti_firebase_app_id = '"'.eti_get_settings("eti_firebase_app_id").'"';
		$eti_firebase_measurement_id = '"'.eti_get_settings("eti_firebase_measurement_id").'"';
		$eti_firebase_vapid_key = '"'.eti_get_settings("eti_firebase_vapid_key").'"';
		$eti_maps_default_latitude = eti_get_settings("eti_maps_default_latitude");
		$eti_maps_default_longitude = eti_get_settings("eti_maps_default_longitude");
		$eti_firebase_server_key = '"'.eti_get_settings("eti_firebase_server_key").'"';
		$eti_firebase_topic = '"'.eti_get_settings("eti_firebase_topic").'"';

		$eti_maps_key = eti_get_settings("eti_maps_key");
	?>
		<?php if(!empty($order_meta['_eclectus_custom_order_courier_id'][0]) && in_array($order->get_status(),$status_accept)) :?>
		<div class="duration">
			<table class="duration-table">
				<thead>
					<tr>
						<th width="50%">Remaining Distance</th>
						<th width="50%">Estimated Time of Arrival</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><span id="estimated_distance"></span></td>
						<td><span id="estimated_eta"></span></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div id="trackOrderMap"></div>
		<?php endif;?>
		<input type="hidden" id="total_distance" value="0">
		<script src="https://maps.googleapis.com/maps/api/js?libraries=places&key=<?php echo $eti_maps_key?>&callback=initMap" async defer></script>

			<script> 

				var lng, lat, geocoder, marker;
				var courier_id = <?php echo $order_meta['_eclectus_custom_order_courier_id'][0];?>;
				var delivery_lat = <?php echo $shipping_coordinates_lat;?>;
				var delivery_lng = <?php echo $shipping_coordinates_lng;?>;
				var firebase_topic = <?php echo $eti_firebase_topic?>;
				//Route
				// var directionsDisplay;
				// var directionsService;
				var point_A, point_B, request_route;
				
				//Duration
				var duration, origin, destination, total_distance;
				//Icon Markers

				function initMap() {
					
					(function($) {
						
						lat = <?php echo $eti_maps_default_latitude;?>;
						lng = <?php echo $eti_maps_default_longitude;?>;

						const directionsService = new google.maps.DirectionsService();
						const directionsRenderer = new google.maps.DirectionsRenderer({preserveViewport:true,suppressMarkers : true});
						const map = new google.maps.Map(document.getElementById("trackOrderMap"), {
							zoom: 17,
							center: { lat: lat, lng: lng },
							streetViewControl:false,
							fullscreenControl: false,
							mapTypeControl: false
						});
						directionsRenderer.setMap(map);

						
						var icons = {
							start: {
									path: "M0,47.82c.13,0,.18-.09.25-.16.39-.4.78-.81,1.18-1.21l2.1-2.11,1.05-1.06,2.3-2.35c.75-.76,1.5-1.51,2.24-2.27l1.14-1.15,3.33-3.38,1.27-1.29,1.24-1.26,2.08-2.12c.75-.77,1.49-1.54,2.25-2.3.4-.42.82-.82,1.23-1.23s.9-.95,1.36-1.42l.82-.82,2-2c.47-.47.94-.93,1.4-1.41l1.39-1.44L30.42,17l1.71-1.76,1.49-1.5L36.39,11l1.24-1.26c.89-.91,1.78-1.83,2.68-2.74l1.77-1.78c.65-.66,1.29-1.33,1.93-2,.47-.47,1-.94,1.42-1.42L47,.15A.43.43,0,0,1,47.38,0h8.89a.43.43,0,0,1,.33.14c.54.56,1.1,1.11,1.66,1.67l1,1a1.18,1.18,0,0,0,.18.13v7.5a.76.76,0,0,1-.16-.06l-.8-.84A.5.5,0,0,0,58,9.33H56.63a.38.38,0,0,0-.31.14l-.5.5L54,11.83c-.3.31-.6.63-.92.92a.7.7,0,0,0-.23.56q0,4.08,0,8.16a1.62,1.62,0,0,0,0,.22.34.34,0,0,1-.11.25c-.29.29-.56.59-.84.88l-2,2-1.24,1.25L47,27.83l-1.62,1.63L42.76,32.1c-.64.66-1.29,1.31-1.94,2l-1.39,1.41a.38.38,0,0,1-.19.09H21.17a.38.38,0,0,0-.31.13c-.34.36-.68.73-1,1.08-.58.6-1.17,1.18-1.76,1.77L15.65,41c-.52.53-1,1.07-1.55,1.59l-1.74,1.75-1.85,1.9L9.1,47.64c-.1.1-.18.21-.28.31H0ZM53.69,3.44a1.22,1.22,0,0,0-1.21-1.25,1.2,1.2,0,0,0-1.22,1.23,1.22,1.22,0,1,0,2.43,0Z",
									fillColor: '#f04c24',
									fillOpacity: 1,
									strokeWeight: 0,
									rotation: 0,
									scale: 1,
									anchor: new google.maps.Point(40, 30),
								},

							end: {
									path: "M0,10.32C0,9.76,0,9.2,0,8.64c0.54-2.59,1.59-4.87,3.71-6.56C5.21,0.89,6.97,0.41,8.77,0c0.47,0,0.95,0,1.42,0 c0.3,0.07,0.6,0.17,0.9,0.21c5.47,0.88,9.21,6.73,7.37,11.86c-1.73,4.81-4.7,8.8-8.74,11.92c-0.16,0-0.32,0-0.47,0 C4.95,20.28,1.56,15.93,0,10.32z M9.45,17.27c4.18,0.01,7.57-3.34,7.6-7.52c0.03-4.26-3.34-7.69-7.56-7.69 c-4.14,0-7.56,3.43-7.57,7.58C1.92,13.8,5.33,17.26,9.45,17.27z M9.63,4.11C8,5.79,6.43,7.41,4.84,9.06 c0.31,0.07,0.62,0.14,1.04,0.23c0,1.47,0,2.88,0,4.33c2.59,0,5.05,0,7.61,0c0-1.48,0-2.9,0-4.35c0.39-0.09,0.69-0.15,1.31-0.29 c-1.93-0.88-1.76-2.44-1.76-4.1c-0.72,0.19-1.27,0.33-2.06,0.54C10.66,5.1,10.15,4.61,9.63,4.11z",
									fillColor: '#00b44e',
									fillOpacity: 1,
									strokeWeight: 0,
									rotation: 0,
									scale: 2,
									anchor: new google.maps.Point(10, 25),
								}
								
						};
						
						const start_marker = new google.maps.Marker({
								position: new google.maps.LatLng(lat, lng),
								map: map,
								icon: icons.start
							});

						const end_marker = new google.maps.Marker({
							position: new google.maps.LatLng(delivery_lat, delivery_lng),
							map: map,
							icon: icons.end
						});

						function onchange_coordinates(){
							calculateAndDisplayRoute(directionsService, directionsRenderer);
						}

						//Create Duration
						function create_duration(){
							duration = new google.maps.DistanceMatrixService();
							origin = {lat: lat, lng: lng};
							destination = {lat: delivery_lat, lng: delivery_lng};
							request_duration = {
								origins: [origin],
								destinations: [destination],
								travelMode: 'DRIVING',
								unitSystem: google.maps.UnitSystem.METRIC
							}
							duration.getDistanceMatrix(request_duration, function(response, status) {
								if (status == 'OK') {
									const originAddresses = response.originAddresses;

									for (let i = 0; i < originAddresses.length; i++) {
										const results = response.rows[i].elements;

										for (let j = 0; j < results.length; j++) {
											// console.log(results[j]);
											$('#estimated_distance').text(results[j].distance.text);
											$('#estimated_eta').text(results[j].duration.text);
											$('#total_distance').val(results[j].distance.value);
										}
									}
								} 
								else {
									alert("Error was: " + status);
									console.log("Error was: " + status);
								}
								total_distance = parseFloat(total_distance);
							});
						}
						create_duration(lat, lng, delivery_lat, delivery_lng);

						//Courier Current Coordinates
						function move_marker(m_lat,m_lng){
							// marker.setVisible(false);

							var total_dist = parseFloat(document.getElementById('total_distance').value);

							move_lat = parseFloat(m_lat); 
							move_lng = parseFloat(m_lng);
							var pos = {
								lat: move_lat,
								lng: move_lng
							};

							calculateAndDisplayRoute(move_lat, move_lng, directionsService, directionsRenderer);
							
							map.setCenter(pos);
						}

						/*
						// 	*
						// 	Start Of Firebase
						// 	*
						// 	*/
						var config = {
							apiKey: <?php echo $eti_firebase_api_key;?>,
							authDomain: <?php echo $eti_firebase_auth_domain;?>,
							databaseURL: <?php echo  $eti_firebase_database_url;?>,
							projectId: <?php echo  $eti_firebase_project_id;?>,
							storageBucket: <?php echo  $eti_firebase_storage_bucket;?>,
							messagingSenderId: <?php echo $eti_firebase_messaging_sender_id;?>,
							appId: <?php echo $eti_firebase_app_id;?>,
							measurementId: <?php echo $eti_firebase_measurement_id;?>
						};

						firebase.initializeApp(config);

						const messaging = firebase.messaging();

						messaging.onMessage((payload) => {
							console.log('Message received. ', payload);
							move_marker(payload.data['lat'],payload.data['long']);
							map.panBy(0, 0);
						});

						function resetUI() {
							messaging.getToken({
								vapidKey: <?php echo $eti_firebase_vapid_key;?>
							}).then((currentToken) => {
								if (currentToken) {
									//Topic Subscriptopn and unsubscribe
									<?php if($order->get_status() == 'toship' || $order->get_status() == 'shipping'):?>
										sendTokenToServer(currentToken);
										subscribeToTopic(currentToken);
									<?php else:?>
										unsubscribe_topic(currentToken);
									<?php endif;?>
								} else {
									// Show permission request.
									console.log('No registration token available. Request permission to generate one.');
									setTokenSentToServer(false);
								}
							}).catch((err) => {
								console.log('An error occurred while retrieving token. ', err);
								// showToken('Error retrieving registration token. ', err);
								setTokenSentToServer(false);
							});
						}
						function sendTokenToServer(currentToken) {
							if (!isTokenSentToServer()) {
								console.log('Sending token to server...');
								// TODO(developer): Send the current token to your server.
								setTokenSentToServer(true);
							} else {
								console.log('Token already sent to server so won\'t send it again ' + 'unless it changes');
							}
						}

						function isTokenSentToServer() {
							return window.localStorage.getItem('sentToServer') === '1';
						}

						function setTokenSentToServer(sent) {
							window.localStorage.setItem('sentToServer', sent ? '1' : '0');
						}

						resetUI();

						function subscribeToTopic(currentToken) {
							var key = <?php echo $eti_firebase_server_key;?>;
							var topic = firebase_topic+courier_id;
							fetch('https://iid.googleapis.com/iid/v1/' + currentToken + '/rel/topics/' + topic, {
								'method': 'POST',
								'headers': {
									'Authorization': 'key=' + key,
									'Content-Type': 'application/json'
								}
							}).then(function(response) {
								// console.log(response);
							}).catch(function(error) {
								console.error(error);
							});
						}

						function unsubscribe_topic(currentToken){
							var key = <?php echo $eti_firebase_server_key;?>;
							var topic = '/topics/'+firebase_topic+courier_id;
							var reg_tokens = [currentToken];
							fetch('https://iid.googleapis.com/iid/v1:batchRemove', {
								'method': 'POST',
								'headers': {
									'Authorization': 'key=' + key,
									'Content-Type': 'application/json'
								},
								'body':JSON.stringify({
									'to': topic,
									"registration_tokens": reg_tokens
								})
							}).then(function(response) {
								// console.log(response);
							}).catch(function(error) {
								console.error(error);
							});
						}
						
						

						var icons2 = {
							start: new google.maps.MarkerImage(
							// URL
							'https://uat.dfdelivers.net/wp-content/uploads/2021/07/eti_logo_new2.png',
							// (width,height)
							new google.maps.Size( 44, 32 ),
							// The origin point (x,y)
							new google.maps.Point( 0, 0 ),
							// The anchor point (x,y)
							new google.maps.Point( 22, 32 )
							),
							end: new google.maps.MarkerImage(
							// URL
							'https://uat.dfdelivers.net/wp-content/uploads/2021/07/home_icon2.png',
							// (width,height)
							new google.maps.Size( 44, 32 ),
							// The origin point (x,y)
							new google.maps.Point( 0, 0 ),
							// The anchor point (x,y)
							new google.maps.Point( 22, 32 )
							)
						};
						function makeMarker(position,place){
							if(place == 'start'){
								
								start_marker.setPosition(position);
							}
							if(place == 'end'){
								
								end_marker.setPosition(position);
							}

						}
						function calculateAndDisplayRoute(lat, lng, directionsService, directionsRenderer) {

							point_A = new google.maps.LatLng(lat, lng);
							point_B = new google.maps.LatLng(delivery_lat, delivery_lng);
							var total_dist = document.getElementById('total_distance').value;

							var route_options = {
									origin: point_A,
									destination: point_B,
									travelMode: google.maps.TravelMode.DRIVING,
								}
							directionsService.route(route_options,function( response, status ) {
								if ( status == google.maps.DirectionsStatus.OK ) {
								directionsRenderer.setDirections( response );
									var leg = response.routes[ 0 ].legs[ 0 ];
									
									$('#estimated_distance').text(leg.distance.text);
									$('#estimated_eta').text(leg.duration.text);
									makeMarker(leg.start_location,'start');
									makeMarker(leg.end_location,'end');

									var covered_dist = leg.distance.value;
									
									var percentage_rem = 0;
									var percentage = 0;

									
									percentage_rem = ((total_dist-covered_dist)/total_dist)*140;
									percentage = percentage_rem;
									console.log('Directions calc: '+percentage );

									$('#complete-progressbar').css('width',percentage+'%');
								}
								else{
									console.log('Error: '+status)
								}
							});
						}
					})(jQuery); 
				}

				

				
			</script>
		<?php
	}
	add_shortcode('track_order_map','track_order_map_function');