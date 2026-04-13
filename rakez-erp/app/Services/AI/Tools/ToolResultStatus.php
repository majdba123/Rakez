<?php

namespace App\Services\AI\Tools;

enum ToolResultStatus: string
{
    case Success = 'success';
    case InsufficientData = 'insufficient_data';
    case Denied = 'denied';
    case InvalidArguments = 'invalid_arguments';
    case UnsupportedOperation = 'unsupported_operation';
    case Error = 'error';
}
