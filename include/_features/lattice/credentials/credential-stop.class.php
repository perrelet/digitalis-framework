<?php

namespace Lattice;

// An Error, not an Exception: plugins catch Exception around their own writes (A9).
class Credential_Stop extends \Error {}
