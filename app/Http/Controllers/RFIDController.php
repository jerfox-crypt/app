<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RFIDController extends Controller
{
    public function handleScan(Request $request)
    {
        // Handle both GET and POST requests
        if ($request->isMethod('get')) {
            // GET request: get rfid from query parameter
            $rfid = trim($request->query('rfid', ''));

            if (empty($rfid)) {
                return response()->json([
                    'success' => false,
                    'error' => 'RFID_REQUIRED',
                    'message' => 'RFID parameter is required'
                ], 400);
            }
        } else {
            // POST request: validate the request
            $validated = $request->validate([
                'rfid' => 'required|string|max:50',
            ]);
            $rfid = trim($validated['rfid']);
        }

        Log::info('RFID Scan Attempt', [
            'rfid' => $rfid,
            'ip' => $request->ip(),
            'method' => $request->method()
        ]);

        $teacher = DB::table('teacher')->where('rfid', $rfid)->first();

        if ($teacher) {
            // It's a teacher
            $personData = $this->formatTeacherData($teacher);
            $personType = 'teacher';
            $personId = $teacher->id;
        } else {
            $student = DB::table('studinfo')->where('rfid', $rfid)->first();

            if ($student) {
                $personData = $this->formatStudentData($student);
                $personType = 'student';
                $personId = $student->id;
            } else {
                $this->logScan($rfid, null, 'not_found', 'RFID not found in database');

                return response()->json([
                    'success' => false,
                    'error' => 'RFID_NOT_FOUND',
                    'message' => 'RFID card not registered in the system'
                ], 404);
            }
        }

        $this->logScan($rfid, $personId, 'success', 'Scan successful', [
            'person_type' => $personType,
            'data' => $personData
        ]);

        // Return success response
        return response()->json([
            'success' => true,
            'data' => $personData,
            'person_type' => $personType,
            'scan_time' => now()->format('Y-m-d H:i:s')
        ]);
    }

    private function formatTeacherData($teacher)
    {
        $fullName = $teacher->firstname . ' ' . $teacher->middlename . ' ' . $teacher->lastname;
        $fullName = trim($fullName);

        $photoUrl = null;
        if ($teacher->picurl) {
            $filename = pathinfo($teacher->picurl, PATHINFO_FILENAME);
            $photoUrl = asset('storage/employeeprofile/2020-2021/' . $filename . '.png');
        }

        return [
            'id' => $teacher->id,
            'fullname' => $fullName,
            'lastname' => $teacher->lastname,
            'firstname' => $teacher->firstname,
            'middlename' => $teacher->middlename,
            'photo_url' => $photoUrl,
            'person_type' => 'teacher',
            'label' => 'Teacher'
        ];
    }

    private function formatStudentData($student)
    {
        // Build full name
        $fullName = $student->lastname . ', ' . $student->firstname;
        if ($student->middlename) {
            $fullName .= ' ' . substr($student->middlename, 0, 1) . '.';
        }
        if ($student->suffix) {
            $fullName .= ' ' . $student->suffix;
        }

        // Determine primary contact
        $primaryContact = null;
        if (!empty($student->mcontactno)) {
            $primaryContact = $student->mcontactno;
        } elseif (!empty($student->fcontactno)) {
            $primaryContact = $student->fcontactno;
        } elseif (!empty($student->gcontactno)) {
            $primaryContact = $student->gcontactno;
        }

        return [
            'id' => $student->id,
            'sid' => $student->sid,
            'lrn' => $student->lrn,
            'fullname' => $fullName,
            'lastname' => $student->lastname,
            'firstname' => $student->firstname,
            'middlename' => $student->middlename,
            'levelname' => $student->levelname,
            'sectionname' => $student->sectionname,
            'level_section' => $student->levelname . '' . $student->sectionname,
            'gender' => $student->gender,
            'photo_url' => $student->picurl,
        ];
    }

    private function isDuplicateScan($studentId)
    {
        $fiveMinutesAgo = Carbon::now()->subMinutes(5);

        return DB::table('taphistory')
            ->where('studid', $studentId)
            ->where('tapstatus', 1)
            ->where('createddatetime', '>', $fiveMinutesAgo)
            ->exists();
    }

    private function isRateLimited($rfid)
    {
        // Allow max 10 scans per minute per RFID
        $oneMinuteAgo = Carbon::now()->subMinute();

        $scanCount = DB::table('taphistory')
            ->where('rfid', $rfid)
            ->where('createddatetime', '>', $oneMinuteAgo)
            ->count();

        return $scanCount >= 10;
    }

    private function logScan($rfid, $personId, $status, $message, $metadata = null)
    {
        \Log::info('logScan START', [
            'rfid' => $rfid,
            'personId' => $personId,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata
        ]);

        // Handle error status - always log errors
        if ($status === 'failed' || $status === 'error' || $status === 'not_found') {
            \Log::info('Inserting error record to taphistory', [
                'rfid' => $rfid,
                'personId' => $personId,
                'status' => $status
            ]);

            // Insert error record to taphistory
            DB::table('taphistory')->insert([
                'tdate' => now()->format('Y-m-d'),
                'ttime' => now()->format('H:i'),
                'tapstate' => 'ERROR',
                'studid' => $personId,
                'createddatetime' => now(),
                'createdby' => auth()->id() ?? null,
                'tapstatus' => 0,
                'deleted' => 0,
                'utype' => 1
            ]);

            \Log::info('Error record inserted to taphistory');
            return;
        }

        // For successful scans, check if we should log
        if ($personId) {
            \Log::info('Processing successful scan', [
                'personId' => $personId,
                'person_type' => $metadata['person_type'] ?? null
            ]);

            // Tap State Logic
            $lastTap = DB::table('tapbunker')
                ->where('rfid', $rfid)
                ->orderBy('createddatetime', 'desc')
                ->first();

            \Log::info('Last tap query result', [
                'rfid' => $rfid,
                'lastTap' => $lastTap ? [
                    'tapstate' => $lastTap->tapstate,
                    'createddatetime' => $lastTap->createddatetime,
                    'rfid' => $lastTap->rfid
                ] : null,
                'hasLastTap' => !is_null($lastTap)
            ]);

            $tapstate = 'IN';

            if ($lastTap) {
                // 2. Calculate time difference in minutes (use absolute value)
                $lastTapTime = Carbon::parse($lastTap->createddatetime);
                $timeDifferenceMinutes = abs(now()->diffInMinutes($lastTapTime));

                \Log::info('Time difference calculation', [
                    'lastTapTime' => $lastTapTime,
                    'currentTime' => now(),
                    'timeDifferenceMinutes' => $timeDifferenceMinutes
                ]);

                // 3. If > 1 minute → toggle IN/OUT If ≤ 1 minute → keep same state
                if ($timeDifferenceMinutes > 1) {
                    $tapstate = ($lastTap->tapstate === 'IN') ? 'OUT' : 'IN';
                    \Log::info('Time > 1 minute, toggling state', [
                        'oldState' => $lastTap->tapstate,
                        'newState' => $tapstate
                    ]);
                } else {
                    $tapstate = $lastTap->tapstate;
                    \Log::info('Time ≤ 1 minute, keeping same state', [
                        'state' => $tapstate,
                        'timeDifference' => $timeDifferenceMinutes
                    ]);
                    return;
                }
            }

            // Determine utype based on person_type
            $utype = 1;
            if (isset($metadata['person_type'])) {
                $utype = $metadata['person_type'] === 'teacher' ? 7 : 1;
                \Log::info('Setting utype', [
                    'person_type' => $metadata['person_type'],
                    'utype' => $utype
                ]);
            }

            \Log::info('Inserting into taphistory', [
                'tapstate' => $tapstate,
                'studid' => $personId,
                'utype' => $utype
            ]);

            // Insert into taphistory
            DB::table('taphistory')->insert([
                'tdate' => now()->format('Y-m-d'),
                'ttime' => now()->format('H:i'),
                'tapstate' => $tapstate,
                'studid' => $personId,
                'createddatetime' => now(),
                'createdby' => auth()->id() ?? null,
                'tapstatus' => 1,
                'deleted' => 0,
                'utype' => $utype // 1 for student, 7 for teacher
            ]);

            \Log::info('taphistory record inserted');

            // Log to tapbunker for both students AND teachers
            if (isset($metadata['person_type'])) {
                \Log::info('Calling logToTapbunker', [
                    'personId' => $personId,
                    'tapstate' => $tapstate,
                    'person_type' => $metadata['person_type']
                ]);
                $this->logToTapbunker($rfid, $personId, $tapstate, $metadata);
            }
        } else {
            \Log::warning('No personId provided, skipping scan logging');
        }

        \Log::info('logScan END');
    }

    private function logToTapbunker($rfid, $personId, $tapstate, $metadata = null)
    {
        \Log::info('logToTapbunker START', [
            'rfid' => $rfid,
            'personId' => $personId,
            'tapstate' => $tapstate,
            'person_type' => $metadata['person_type'] ?? 'unknown'
        ]);

        $setup = DB::table('setup')->first();
        $school = $setup->school ?? 'MAC';

        $person = null;
        $isTeacher = false;

        // Check if it's a teacher or student
        if (isset($metadata['person_type']) && $metadata['person_type'] === 'teacher') {
            $person = DB::table('teacher')->where('id', $personId)->first();
            $isTeacher = true;
        } else {
            $person = DB::table('studinfo')->where('id', $personId)->first();
        }

        if (!$person)
            return;

        $time = now()->format('h:i A');
        $message = '';

        // E. Build SMS Message Student & Teacher
        if ($isTeacher) {
            $message = "{$school}: Teacher {$person->firstname} tapped {$tapstate} at {$time}";
        } else {
            $location = ($tapstate === 'IN') ? 'inside' : 'outside';
            $message = "{$school}: Your student {$person->firstname} is already {$location} the school campus at {$time}";
        }

        // Determine receiver and format to +63 (only for students)
        $receiver = null;
        $rawNumber = null;

        if (!$isTeacher) {
            // Get raw number based on priority (students only)
            if (!empty($person->mcontactno)) {
                $rawNumber = $person->mcontactno;
            } elseif (!empty($person->fcontactno)) {
                $rawNumber = $person->fcontactno;
            } elseif (!empty($person->gcontactno)) {
                $rawNumber = $person->gcontactno;
            }

            // Format to +63 format
            if ($rawNumber) {
                $receiver = $this->formatTo63($rawNumber);
            }
        }

        // Insert into tapbunker
        DB::table('tapbunker')->insert([
            'message' => $message,
            'receiver' => $receiver,
            'smsstatus' => 0,
            'createddatetime' => now(),
            'rfid' => $rfid,
            'tapstate' => $tapstate,
            'xml' => $school
        ]);

        \Log::info('tapbunker record inserted', [
            'message' => $message,
            'receiver' => $receiver,
            'tapstate' => $tapstate,
            'person_type' => $isTeacher ? 'teacher' : 'student'
        ]);

        \Log::info('logToTapbunker END');
    }

    private function formatTo63($phoneNumber)
    {
        $clean = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (empty($clean))
            return null;

        $clean = ltrim($clean, '0');
        $clean = (substr($clean, 0, 2) === '63') ? $clean : '63' . $clean;

        return '+' . $clean;
    }

    // Optional: Get recent scans for dashboard
    public function getRecentScans()
    {
        $scans = DB::table('taphistory')
            ->where('tapstatus', 1)
            ->orderBy('createddatetime', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $scans
        ]);
    }
}