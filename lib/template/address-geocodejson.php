<?php

// https://github.com/geocoders/geocodejson-spec/

$aFilteredPlaces = array();

if (empty($aPlace)) {
    if (isset($sError)) {
        $aFilteredPlaces['error'] = $sError;
    } else {
        $aFilteredPlaces['error'] = 'Unable to geocode';
    }
    javascript_renderData($aFilteredPlaces);
} else {
    $aFilteredPlaces = array(
                        'type' => 'Feature',
                        'properties' => array(
                                         'geocoding' => array()
                                        )
                       );

    if (isset($aPlace['place_id'])) {
        $aFilteredPlaces['properties']['geocoding']['place_id'] = $aPlace['place_id'];
    }

    $aFilteredPlaces['properties']['geocoding']['licence'] = $aPlace['licence'];

    $aFilteredPlaces['properties']['geocoding']['copyright'] = $aPlace['copyright'];
                                 
    $sOSMType = formatOSMType($aPlace['osm_type']);
    if ($sOSMType) {
        $aFilteredPlaces['properties']['geocoding']['osm_type'] = $sOSMType;
        $aFilteredPlaces['properties']['geocoding']['osm_id'] = $aPlace['osm_id'];
    }

    $aFilteredPlaces['properties']['geocoding']['type'] = $aPlace['type'];

    $aFilteredPlaces['properties']['geocoding']['accuracy'] = (int) $fDistance;

    $aFilteredPlaces['properties']['geocoding']['label'] = $aPlace['langaddress'];

    if ($aPlace['placename'] !== null) {
        $aFilteredPlaces['properties']['geocoding']['name'] = $aPlace['placename'];
    }

    if (isset($aPlace['address'])) {
        $aPlace['address']->addGeocodeJsonAddressParts(
            $aFilteredPlaces['properties']['geocoding']
        );

        $aFilteredPlaces['properties']['geocoding']['admin']
            = $aPlace['address']->getAdminLevels();
    }

    if (isset($aPlace['asgeojson'])) {
        $aFilteredPlaces['geometry'] = json_decode($aPlace['asgeojson']);
    } else {
        $aFilteredPlaces['geometry'] = array(
                                        'type' => 'Point',
                                        'coordinates' => array(
                                                          (float) $aPlace['lon'],
                                                          (float) $aPlace['lat']
                                                         )
                                       );
    }

    javascript_renderData(
        array(
         'type' => 'FeatureCollection',
         'geocoding' => array(
                         'version' => '0.1.0',
                        //  'attribution' => 'Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright',
                        //    'licence' => 'ODbL',
                         'query' => $sQuery
                                          ),
         'features' => array($aFilteredPlaces)
        )
    );
}
