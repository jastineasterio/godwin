<?php

namespace App\Http\Controllers\MyClasses;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MY CLASSES — Teachers view only the classes assigned to them.
 */
class MyClassesController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();

        $classIds = $teacher->assignedClassIds();

        $classes = SchoolClass::whereIn('id', $classIds)
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->with('subjects:id,name,code')
            ->ordered()
            ->get(['id', 'name', 'code', 'level', 'teacher_id', 'capacity', 'description']);

        return Inertia::render('MyClasses/Index', [
            'classes' => $classes->map(fn (SchoolClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'level' => $c->level?->value,
                'capacity' => $c->capacity,
                'students_count' => $c->students_count,
                'description' => $c->description,
                'subjects' => $c->subjects->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'code' => $s->code,
                ])->all(),
                'teacher' => [
                    'id' => $c->teacher_id,
                    'name' => $c->teacher?->name,
                ],
            ])->all(),
        ]);
    }

    public function students(Request $request): Response
    {
        $teacher = $request->user();

        $classIds = $teacher->classesTaught()->pluck('classes.id')->all();

        $students = Student::whereIn('class_id', $classIds)
            ->where('status', 'active')
            ->with('class:id,name,code')
            ->orderBy('first_name')
            ->get(['id', 'reg_no', 'first_name', 'last_name', 'other_name', 'gender', 'dob', 'class_id']);

        return Inertia::render('MyClasses/Students', [
            'students' => $students->map(fn (Student $s) => [
                'id' => $s->id,
                'reg_no' => $s->reg_no,
                'name' => $s->full_name,
                'gender' => $s->gender->value,
                'dob' => $s->dob?->toDateString(),
                'class' => $s->class?->name,
                'class_code' => $s->class?->code,
                'age' => $s->age,
            ])->all(),
            'classIds' => $classIds,
        ]);
    }

    public function show(Request $request, SchoolClass $class): Response
    {
        $teacher = $request->user();

        // Teacher must be assigned to this class
        abort_unless(
            $teacher->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin])
                || $class->teacher_id === $teacher->id,
            403,
            'You are not the class teacher for this class.'
        );

        $students = Student::where('class_id', $class->id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'reg_no', 'first_name', 'last_name', 'other_name', 'gender', 'dob', 'admission_date']);

        return Inertia::render('MyClasses/Show', [
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'level' => $class->level?->value,
                'capacity' => $class->capacity,
                'teacher' => $class->teacher?->name,
            ],
            'students' => $students->map(fn (Student $s) => [
                'id' => $s->id,
                'reg_no' => $s->reg_no,
                'name' => $s->full_name,
                'gender' => $s->gender->value,
                'dob' => $s->dob?->toDateString(),
                'admission_date' => $s->admission_date?->toDateString(),
                'age' => $s->age,
            ])->all(),
        ]);
    }
}
