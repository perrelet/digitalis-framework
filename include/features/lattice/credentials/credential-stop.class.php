<?php

namespace Digitalis;

// A refusal inside one of the layer's writes. An Error, not an Exception, so a plugin catching Exception around its own write cannot swallow it; the layer catches it, journals what landed and answers (A9).
class Credential_Stop extends \Error {}
