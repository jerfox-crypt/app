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
        // Validate the request
        $validated = $request->validate([
            'rfid' => 'required|string|max:50',
        ]);

        $rfid = trim($validated['rfid']);

        Log::info('RFID Scan Attempt', [
            'rfid' => $rfid,
            'ip' => $request->ip()
        ]);

        // CHECK TEACHER FIRST (since teacher RFID shouldn't be in student table)
        $teacher = DB::table('teacher')->where('rfid', $rfid)->first();

        if ($teacher) {
            // It's a teacher
            $personData = $this->formatTeacherData($teacher);
            $personType = 'teacher';
            $personId = $teacher->id;
        } else {
            // Not a teacher, check if student
            $student = DB::table('studinfo')->where('rfid', $rfid)->first();

            if ($student) {
                // It's a student
                $personData = $this->formatStudentData($student);
                $personType = 'student';
                $personId = $student->id;
            } else {
                // Neither teacher nor student
                $this->logScan($rfid, null, 'not_found', 'RFID not found in database');

                return response()->json([
                    'success' => false,
                    'error' => 'RFID_NOT_FOUND',
                    'message' => 'RFID card not registered in the system'
                ], 404);
            }
        }

        // Log successful scan
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
        // Build full name
        $fullName = $teacher->firstname . ' ' . $teacher->middlename . ' ' . $teacher->lastname;
        $fullName = trim($fullName);

        // Fix photo URL - always use .png extension
        $photoUrl = null;
        if ($teacher->picurl) {
            // Get filename without extension
            $filename = pathinfo($teacher->picurl, PATHINFO_FILENAME);

            // Always use .png extension
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
            'suffix' => $student->suffix,
            'levelname' => $student->levelname,
            'sectionname' => $student->sectionname,
            'level_section' => $student->levelname . '' . $student->sectionname,
            'gender' => $student->gender,
            'photo_url' => $student->picurl,
            'primary_contact' => $primaryContact,
            'mcontactno' => $student->mcontactno,
            'fcontactno' => $student->fcontactno,
            'gcontactno' => $student->gcontactno,
            'ismothernum' => $student->ismothernum,
            'isfathernum' => $student->isfathernum,
            'isguardannum' => $student->isguardannum
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
        // Handle error status - always log errors
        if ($status === 'failed' || $status === 'error' || $status === 'not_found') {
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
            return;
        }

        // For successful scans, check if we should log
        if ($personId) {
            // Check last tap
            $lastTap = DB::table('taphistory')
                ->where('studid', $personId)
                ->where('deleted', 0)
                ->orderBy('createddatetime', 'desc')
                ->first();

            if ($lastTap) {
                $lastTapTime = Carbon::parse($lastTap->createddatetime);
                $timeDifference = abs(now()->diffInSeconds($lastTapTime));

                // If within 1 minute, don't insert new record
                if ($timeDifference < 60) {
                    return;
                }

                // Determine new tapstate (toggle if over 1 minute)
                $tapstate = ($lastTap->tapstate === 'IN') ? 'OUT' : 'IN';
            } else {
                // No previous tap, default to IN
                $tapstate = 'IN';
            }

            // Determine utype based on person_type
            $utype = 1;
            if (isset($metadata['person_type'])) {
                $utype = $metadata['person_type'] === 'teacher' ? 7 : 1;
            }

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

            // Only log to tapbunker if it's a STUDENT
            if (isset($metadata['person_type']) && $metadata['person_type'] === 'student') {
                $this->logToTapbunker($rfid, $personId, $tapstate, $metadata);
            }
        }
    }

    private function logToTapbunker($rfid, $studentId, $tapstate, $metadata = null)
    {
        // Get setup data
        $setup = DB::table('setup')->first();

        if (!$setup) {
            $school = 'MAC'; // Default fallback
        } else {
            $school = $setup->school ?? 'MAC';
        }

        // Get student info
        $student = DB::table('studinfo')->where('id', $studentId)->first();

        if (!$student)
            return;

        // Determine location text
        $location = ($tapstate === 'IN') ? 'inside' : 'outside';
        $time = now()->format('h:i A');

        // Create message
        $message = "{$school}: Your student {$student->firstname} is already {$location} the school campus at {$time}";

        // Determine receiver and format to +63
        $receiver = null;
        $rawNumber = null;

        // Get raw number based on priority
        if (!empty($student->mcontactno)) {
            $rawNumber = $student->mcontactno;
        } 
        elseif (!empty($student->fcontactno)) {
            $rawNumber = $student->fcontactno;
        } 
        elseif (!empty($student->gcontactno)) {
            $rawNumber = $student->gcontactno;
        }

        // Format to +63 format
        if ($rawNumber) {
            $receiver = $this->formatTo63($rawNumber);
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

        // Log for debugging
        \Log::info('Tapbunker record created', [
            'school' => $school,
            'student' => $student->firstname,
            'tapstate' => $tapstate,
            'raw_number' => $rawNumber,
            'formatted_receiver' => $receiver,
            'message' => $message
        ]);
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