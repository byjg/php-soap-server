<?php

namespace ByJG\SoapServer;

enum SoapType: string
{
    // Simple types
    case String = 'string';
    case Integer = 'int';
    case Float = 'float';
    case Double = 'double';
    case Boolean = 'bool';

    // Variable types (legacy compatibility)
    case VarString = 'varstring';
    case VarInteger = 'varinteger';
    case VarInt = 'varint';
    case VarFloat = 'varfloat';
    case VarDouble = 'vardouble';
    case VarBoolean = 'varboolean';
    case VarBool = 'varbool';

    // Array types (single dimension)
    case ArrayOfString = 'string[]';
    case ArrayOfInteger = 'int[]';
    case ArrayOfFloat = 'float[]';
    case ArrayOfDouble = 'double[]';
    case ArrayOfBoolean = 'bool[]';

    // Special types
    case Void = 'void';
    case Mixed = 'mixed';
}
