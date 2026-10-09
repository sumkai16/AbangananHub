<?php

/**
 * Cebu landmarks a tenant might say "near X" about (plans/ai-search.md).
 *
 * key      stable id the AI is asked to return
 * name     how the summary and chips show it
 * aliases  lowercase spellings people type (English, Tagalog, Bisaya, short forms)
 * lat/lng  APPROXIMATE, drafted from memory: check each pin on a map before relying on distances
 * city     city_municipality it sits in
 */
return [
    // Schools
    ['key' => 'usc_talamban', 'name' => 'USC Talamban', 'aliases' => ['usc talamban', 'usc tc', 'usc north', 'university of san carlos talamban'], 'lat' => 10.3530, 'lng' => 123.9115, 'city' => 'Cebu City'],
    ['key' => 'usc_main', 'name' => 'USC Main (Downtown)', 'aliases' => ['usc main', 'usc downtown', 'usc dc', 'university of san carlos'], 'lat' => 10.2977, 'lng' => 123.8995, 'city' => 'Cebu City'],
    ['key' => 'up_cebu', 'name' => 'UP Cebu (Lahug)', 'aliases' => ['up cebu', 'up lahug', 'university of the philippines cebu'], 'lat' => 10.3223, 'lng' => 123.8987, 'city' => 'Cebu City'],
    ['key' => 'cit_u', 'name' => 'CIT-U', 'aliases' => ['cit-u', 'cit u', 'cit', 'cebu institute of technology', 'citu'], 'lat' => 10.2955, 'lng' => 123.8812, 'city' => 'Cebu City'],
    ['key' => 'uc_main', 'name' => 'University of Cebu Main', 'aliases' => ['uc main', 'university of cebu', 'uc sanciangko'], 'lat' => 10.2989, 'lng' => 123.8986, 'city' => 'Cebu City'],
    ['key' => 'uv_main', 'name' => 'University of the Visayas Main', 'aliases' => ['uv main', 'uv', 'university of the visayas', 'uv colon'], 'lat' => 10.2957, 'lng' => 123.8984, 'city' => 'Cebu City'],
    ['key' => 'cnu', 'name' => 'Cebu Normal University', 'aliases' => ['cnu', 'cebu normal university', 'normal'], 'lat' => 10.3121, 'lng' => 123.8935, 'city' => 'Cebu City'],
    ['key' => 'swu', 'name' => 'Southwestern University PHINMA', 'aliases' => ['swu', 'swu phinma', 'southwestern university'], 'lat' => 10.2967, 'lng' => 123.8956, 'city' => 'Cebu City'],
    ['key' => 'ctu_main', 'name' => 'Cebu Technological University Main', 'aliases' => ['ctu', 'ctu main', 'cebu technological university'], 'lat' => 10.3028, 'lng' => 123.8925, 'city' => 'Cebu City'],

    // Malls and business districts
    ['key' => 'it_park', 'name' => 'Cebu IT Park', 'aliases' => ['it park', 'cebu it park', 'itpark', 'jy square'], 'lat' => 10.3292, 'lng' => 123.9055, 'city' => 'Cebu City'],
    ['key' => 'ayala_center', 'name' => 'Ayala Center Cebu', 'aliases' => ['ayala', 'ayala center', 'ayala cebu', 'cebu business park', 'cbp'], 'lat' => 10.3187, 'lng' => 123.9050, 'city' => 'Cebu City'],
    ['key' => 'sm_city_cebu', 'name' => 'SM City Cebu', 'aliases' => ['sm city', 'sm city cebu', 'sm north', 'sm cebu'], 'lat' => 10.3115, 'lng' => 123.9184, 'city' => 'Cebu City'],
    ['key' => 'sm_seaside', 'name' => 'SM Seaside City Cebu', 'aliases' => ['sm seaside', 'seaside', 'sm seaside city'], 'lat' => 10.2824, 'lng' => 123.8814, 'city' => 'Cebu City'],
    ['key' => 'robinsons_galleria', 'name' => 'Robinsons Galleria Cebu', 'aliases' => ['galleria', 'robinsons galleria', 'robinsons cebu'], 'lat' => 10.3005, 'lng' => 123.9138, 'city' => 'Cebu City'],
    ['key' => 'parkmall', 'name' => 'Parkmall Mandaue', 'aliases' => ['parkmall', 'park mall', 'parkmall mandaue'], 'lat' => 10.3257, 'lng' => 123.9348, 'city' => 'Mandaue City'],
    ['key' => 'colon', 'name' => 'Colon Street', 'aliases' => ['colon', 'colon street', 'downtown'], 'lat' => 10.2954, 'lng' => 123.9004, 'city' => 'Cebu City'],
    ['key' => 'carbon', 'name' => 'Carbon Market', 'aliases' => ['carbon', 'carbon market'], 'lat' => 10.2933, 'lng' => 123.9011, 'city' => 'Cebu City'],
    ['key' => 'fuente', 'name' => 'Fuente Osmeña', 'aliases' => ['fuente', 'fuente osmena', 'fuente circle'], 'lat' => 10.3115, 'lng' => 123.8914, 'city' => 'Cebu City'],
    ['key' => 'mactan_newtown', 'name' => 'Mactan Newtown', 'aliases' => ['mactan newtown', 'newtown', 'mactan new town'], 'lat' => 10.3225, 'lng' => 124.0195, 'city' => 'Lapu-Lapu City'],

    // Hospitals
    ['key' => 'chong_hua', 'name' => 'Chong Hua Hospital', 'aliases' => ['chong hua', 'chong hua hospital', 'chonghua'], 'lat' => 10.3112, 'lng' => 123.8905, 'city' => 'Cebu City'],
    ['key' => 'vsmmc', 'name' => 'Vicente Sotto Memorial Medical Center', 'aliases' => ['vsmmc', 'vicente sotto', 'sotto hospital'], 'lat' => 10.3029, 'lng' => 123.8886, 'city' => 'Cebu City'],
    ['key' => 'cebu_doctors', 'name' => "Cebu Doctors' Hospital", 'aliases' => ['cebu doctors', 'cebu doctors hospital', 'cdu hospital'], 'lat' => 10.3125, 'lng' => 123.8903, 'city' => 'Cebu City'],

    // Transport
    ['key' => 'south_bus', 'name' => 'Cebu South Bus Terminal', 'aliases' => ['south bus terminal', 'south terminal', 'south bus'], 'lat' => 10.2985, 'lng' => 123.8775, 'city' => 'Cebu City'],
    ['key' => 'north_bus', 'name' => 'Cebu North Bus Terminal', 'aliases' => ['north bus terminal', 'north terminal', 'north bus'], 'lat' => 10.3240, 'lng' => 123.9210, 'city' => 'Cebu City'],
    ['key' => 'mactan_airport', 'name' => 'Mactan-Cebu Airport', 'aliases' => ['airport', 'mactan airport', 'mactan cebu airport', 'mcia'], 'lat' => 10.3071, 'lng' => 123.9794, 'city' => 'Lapu-Lapu City'],
];
