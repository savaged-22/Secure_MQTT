<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case Staff = 'staff';
    case Admin = 'admin';
}
