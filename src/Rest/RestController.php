<?php

/**
 * Greg\Rest\RestController class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Base controller for Greg REST API integration
 */
class RestController {
  const API_NAMESPACE = 'greg/v1';

  /**
   * Register all custom REST routes for the Greg plugin.
   */
  public function register_routes() : void {
    // This incantation means:
    // accept requests like /thing/:id where `:id` is an int.
    register_rest_route(static::API_NAMESPACE, '/thing/(?P<id>\d+)', [
      'methods'  => 'GET',
      'callback' => [$this, 'thing_action'],
    ]);
  }

  /**
   * Handler for the /thing endpoint
   *
   * @param WP_REST_Request the REST request
   * @return WP_REST_Response a REST response
   */
  public function thing_action(WP_REST_Request $request) : WP_REST_Response {
    $response = [
      'success'    => true,
      'data'       => [
        'thing_id' => $request->get_param('id'),
      ],
    ];

    // Return the thing
    return new WP_REST_Response($response);
  }
}
