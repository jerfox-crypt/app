<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\TapHistory;
use App\Models\TapBunker;
use App\Models\TransactionLog;
use App\Models\Setup;

class RFIDController extends Controller
{
    /**
     * Handle POST request for RFID scanning
     */
    public function handleScanPost(Request $request)
    {
        $validated = $request->validate([
            'rfid' => 'required|string|max:50',
        ]);
        $rfid = trim($validated['rfid']);

        return $this->processRFID($rfid, $request);
    }

    /**
     * Handle GET request for RFID scanning
     */
    public function handleScanGet(Request $request)
    {
        $validated = $request->validate([
            'rfid' => 'required|string|max:50',
        ]);
        $rfid = trim($validated['rfid']);

        return $this->processRFID($rfid, $request);
    }

    /**
     * Common RFID processing logic
     */
    private function processRFID($rfid, $request)
    {
        // Use Model instead of DB::table
        TransactionLog::create([
            'rfid' => $rfid,
            'person_id' => null,
            'person_type' => null,
            'status' => 'attempt',
            'message' => 'RFID scan attempt',
            'metadata' => [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent()
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'endpoint' => $request->path(),
        ]);

        // Use Eloquent models instead of DB::table
        $teacher = Teacher::where('rfid', $rfid)->first();

        if ($teacher) {
            $personData = $this->formatTeacherData($teacher);
            $personType = 'teacher';
            $personId = $teacher->id;
        } else {
            $student = Student::where('rfid', $rfid)->first();

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
        $fullName = $student->lastname . ', ' . $student->firstname;
        if ($student->middlename) {
            $fullName .= ' ' . substr($student->middlename, 0, 1) . '.';
        }
        if ($student->suffix) {
            $fullName .= ' ' . $student->suffix;
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

    private function logScan($rfid, $personId, $status, $message, $metadata = null)
    {
        // Use Model
        TransactionLog::create([
            'rfid' => $rfid,
            'person_id' => $personId,
            'person_type' => $metadata['person_type'] ?? null,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => request()->method(),
            'endpoint' => request()->path(),
        ]);

        Log::info('logScan START', [
            'rfid' => $rfid,
            'personId' => $personId,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata
        ]);

        if ($status === 'failed' || $status === 'error' || $status === 'not_found') {
            // Use Model
            TapHistory::create([
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

            Log::info('Error record inserted to taphistory');
            return;
        }

        if ($personId) {
            // Use Model for successful scans
            $lastTap = TapBunker::where('rfid', $rfid)
                ->orderBy('createddatetime', 'desc')
                ->first();

            $tapstate = 'IN';

            if ($lastTap) {
                $lastTapTime = Carbon::parse($lastTap->createddatetime);
                $timeDifferenceMinutes = abs(now()->diffInMinutes($lastTapTime));

                if ($timeDifferenceMinutes > 1) {
                    $tapstate = ($lastTap->tapstate === 'IN') ? 'OUT' : 'IN';
                } else {
                    $tapstate = $lastTap->tapstate;
                    return;
                }
            }

            $utype = isset($metadata['person_type']) && $metadata['person_type'] === 'teacher' ? 7 : 1;

            // Use Model
            TapHistory::create([
                'tdate' => now()->format('Y-m-d'),
                'ttime' => now()->format('H:i'),
                'tapstate' => $tapstate,
                'studid' => $personId,
                'createddatetime' => now(),
                'createdby' => auth()->id() ?? null,
                'tapstatus' => 1,
                'deleted' => 0,
                'utype' => $utype
            ]);

            if (isset($metadata['person_type'])) {
                $this->logToTapbunker($rfid, $personId, $tapstate, $metadata);
            }
        } else {
            Log::warning('No personId provided, skipping scan logging');
        }

        Log::info('logScan END');
    }

    private function logToTapbunker($rfid, $personId, $tapstate, $metadata = null)
    {
        Log::info('logToTapbunker START', [
            'rfid' => $rfid,
            'personId' => $personId,
            'tapstate' => $tapstate,
            'person_type' => $metadata['person_type'] ?? 'unknown'
        ]);

        $setup = Setup::first();
        $school = $setup->school ?? 'MAC';

        $person = null;
        $isTeacher = false;

        if (isset($metadata['person_type']) && $metadata['person_type'] === 'teacher') {
            $person = Teacher::find($personId);
            $isTeacher = true;
        } else {
            $person = Student::find($personId);
        }

        if (!$person) return;

        $time = now()->format('h:i A');
        $message = '';

        if ($isTeacher) {
            $message = "{$school}: Teacher {$person->firstname} tapped {$tapstate} at {$time}";
        } else {
            $location = ($tapstate === 'IN') ? 'inside' : 'outside';
            $message = "{$school}: Your student {$person->firstname} is already {$location} the school campus at {$time}";
        }

        $receiver = null;
        if (!$isTeacher) {
            $rawNumber = $person->mcontactno ?? $person->fcontactno ?? $person->gcontactno ?? null;
            if ($rawNumber) {
                $receiver = $this->formatTo63($rawNumber);
            }
        }

        // Use Model
        TapBunker::create([
            'message' => $message,
            'receiver' => $receiver,
            'smsstatus' => 0,
            'createddatetime' => now(),
            'rfid' => $rfid,
            'tapstate' => $tapstate,
            'xml' => $school
        ]);

        Log::info('tapbunker record inserted', [
            'message' => $message,
            'receiver' => $receiver,
            'tapstate' => $tapstate,
            'person_type' => $isTeacher ? 'teacher' : 'student'
        ]);

        Log::info('logToTapbunker END');
    }

    private function formatTo63($phoneNumber)
    {
        $clean = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (empty($clean)) return null;

        $clean = ltrim($clean, '0');
        $clean = (substr($clean, 0, 2) === '63') ? $clean : '63' . $clean;

        return '+' . $clean;
    }

    public function getRecentScans()
    {
        $scans = TapHistory::where('tapstatus', 1)
            ->orderBy('createddatetime', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $scans
        ]);
    }

    public function getTransactionLogs(Request $request)
    {
        $logs = TransactionLog::orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}