<?php
/**
 * OpenAI moderation client.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_API {

	/**
	 * Moderate a comment before it is saved.
	 *
	 * @param string $comment_text Comment body.
	 * @param bool   $is_reply Whether the comment is a reply.
	 * @param string $parent_comment Optional parent comment content.
	 * @return array<string, mixed>
	 */
	public function moderate_comment( $comment_text, $is_reply = false, $parent_comment = '' ) {
		$api_key = trim( (string) kosher_comments_get_setting( 'openai_api_key', '' ) );

		if ( 'yes' !== kosher_comments_get_setting( 'moderation_enabled', 'yes' ) || '' === $api_key ) {
			return array(
				'is_toxic' => false,
				'reason'   => '',
			);
		}

		$payload = array(
			'model'           => sanitize_text_field( (string) kosher_comments_get_setting( 'moderation_model', 'gpt-4.1-mini' ) ),
			'temperature'     => 0,
			'response_format' => array(
				'type' => 'json_object',
			),
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => 'You are a strict comment moderation assistant. Return valid JSON with keys is_toxic (boolean) and reason (string). Mark threats, harassment, hate, sexual abuse, or severe profanity directed at someone as toxic. Be careful not to flag disagreement that is respectful.',
				),
				array(
					'role'    => 'user',
					'content' => wp_json_encode(
						array(
							'comment_text'   => $comment_text,
							'is_reply'       => (bool) $is_reply,
							'parent_comment' => $parent_comment,
						)
					),
				),
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'is_toxic' => false,
				'reason'   => '',
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code || empty( $body['choices'][0]['message']['content'] ) ) {
			return array(
				'is_toxic' => false,
				'reason'   => '',
			);
		}

		$content = json_decode( $body['choices'][0]['message']['content'], true );

		if ( ! is_array( $content ) ) {
			return array(
				'is_toxic' => false,
				'reason'   => '',
			);
		}

		return array(
			'is_toxic' => ! empty( $content['is_toxic'] ),
			'reason'   => sanitize_text_field( (string) ( $content['reason'] ?? '' ) ),
		);
	}
}
