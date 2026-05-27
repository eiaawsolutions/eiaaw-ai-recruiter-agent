<?php

use App\Services\Outreach\ReplyParser;

beforeEach(function () {
    $this->parser = app(ReplyParser::class);
});

it('detects scheduling intent from common availability phrases', function () {
    expect($this->parser->looksLikeAvailability('Sure — happy to chat. Tuesday 3pm works.'))->toBeTrue();
    expect($this->parser->looksLikeAvailability('Free tomorrow morning?'))->toBeTrue();
    expect($this->parser->looksLikeAvailability('Send me a calendar invite for next week.'))->toBeTrue();
    expect($this->parser->looksLikeAvailability('I am available 14:00 KL time.'))->toBeTrue();
});

it('ignores replies that contain no scheduling cue', function () {
    expect($this->parser->looksLikeAvailability('Please remove me from your list. Thanks.'))->toBeFalse();
    expect($this->parser->looksLikeAvailability('Wrong person.'))->toBeFalse();
});
