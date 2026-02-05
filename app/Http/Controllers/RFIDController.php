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
            'location' => 'nullable|string|max:100'
        ]);

        $rfid = trim($validated['rfid']);
        $location = $validated['location'] ?? 'default';

        Log::info('RFID Scan Attempt', [
            'rfid' => $rfid,
            'location' => $location,
            'ip' => $request->ip()
        ]);

        // Rate limiting check (optional)
        if ($this->isRateLimited($rfid)) {
            return response()->json([
                'success' => false,
                'error' => 'RATE_LIMITED',
                'message' => 'Please wait before scanning again'
            ], 429);
        }

        // Check if RFID exists in studinfo
        $student = DB::table('studinfo')->where('rfid', $rfid)->first();

        if (!$student) {
            // Log failed scan
            $this->logScan($rfid, null, 'not_found', 'RFID not found in database', $location);

            return response()->json([
                'success' => false,
                'error' => 'RFID_NOT_FOUND',
                'message' => 'RFID card not registered in the system'
            ], 404);
        }

        // Format student data for response
        $studentData = $this->formatStudentData($student);

        // Log successful scan
        $this->logScan($rfid, $student->id, 'success', 'Scan successful', $location, $studentData);

        // Return success response
        return response()->json([
            'success' => true,
            'message' => $this->getWelcomeMessage($student),
            'data' => $studentData,
            'scan_time' => now()->format('Y-m-d H:i:s')
        ]);
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
        if ($student->ismothernum == 1 && !empty($student->mcontactno)) {
            $primaryContact = $student->mcontactno;
        } elseif ($student->isfathernum == 1 && !empty($student->fcontactno)) {
            $primaryContact = $student->fcontactno;
        } elseif ($student->isguardannum == 1 && !empty($student->gcontactno)) {
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

    private function getWelcomeMessage($student)
    {
        $greeting = 'Good ' . $this->getTimeOfDayGreeting();
        $name = $student->firstname;

        return "{$greeting}, {$name}! Welcome to " . config('app.name', 'the school');
    }

    private function getTimeOfDayGreeting()
    {
        $hour = now()->hour;

        if ($hour < 12) {
            return 'morning';
        } elseif ($hour < 18) {
            return 'afternoon';
        } else {
            return 'evening';
        }
    }

    private function isDuplicateScan($studentId, $location)
    {
        // Check if same student scanned at same location within last 5 minutes
        $fiveMinutesAgo = Carbon::now()->subMinutes(5);

        return DB::table('scan_logs')
            ->where('student_id', $studentId)
            ->where('location', $location)
            ->where('status', 'success')
            ->where('scan_time', '>', $fiveMinutesAgo)
            ->exists();
    }

    private function isRateLimited($rfid)
    {
        // Allow max 10 scans per minute per RFID
        $oneMinuteAgo = Carbon::now()->subMinute();

        $scanCount = DB::table('scan_logs')
            ->where('rfid', $rfid)
            ->where('scan_time', '>', $oneMinuteAgo)
            ->count();

        return $scanCount >= 10;
    }

    private function logScan($rfid, $studentId, $status, $message, $location, $metadata = null)
    {
        // Handle error status - always log errors
        if ($status === 'failed' || $status === 'error' || $status === 'not_found') {
            // Insert error record
            DB::table('taphistory')->insert([
                'tdate' => now()->format('Y-m-d'),
                'ttime' => now()->format('H:i'),
                'tapstate' => 'ERROR',
                'studid' => $studentId,
                'createddatetime' => now(),
                'createdby' => auth()->id() ?? null,
                'tapstatus' => 0,
                'deleted' => 0
            ]);
            return;
        }

        // For successful scans, check if we should log
        if ($studentId) {
            // Check last tap
            $lastTap = DB::table('taphistory')
                ->where('studid', $studentId)
                ->where('deleted', 0)
                ->orderBy('createddatetime', 'desc')
                ->first();

            if ($lastTap) {
                $lastTapTime = Carbon::parse($lastTap->createddatetime);
                $timeDifference = abs(now()->diffInSeconds($lastTapTime));

                // DEBUG: Log the times
                \Log::info('Time Debug', [
                    'last_tap_time' => $lastTapTime->format('Y-m-d H:i:s'),
                    'current_time' => now()->format('Y-m-d H:i:s'),
                    'time_difference_seconds' => $timeDifference,
                    'last_tapstate' => $lastTap->tapstate,
                    'student_id' => $studentId
                ]);

                // If within 1 minute, don't insert new record
                if ($timeDifference < 60) {
                    \Log::info('Within 1 min, skipping taphistory insertion');
                    return; // Exit function without inserting
                }

                // Determine new tapstate (toggle if over 1 minute)
                $tapstate = ($lastTap->tapstate === 'IN') ? 'OUT' : 'IN';
                \Log::info('Over 1 min, toggling state:', [
                    'old_state' => $lastTap->tapstate,
                    'new_state' => $tapstate
                ]);
            } else {
                // No previous tap, default to IN
                $tapstate = 'IN';
                \Log::info('No previous tap found, defaulting to IN');
            }

            // Insert into taphistory (only if over 1 minute or first tap)
            DB::table('taphistory')->insert([
                'tdate' => now()->format('Y-m-d'),
                'ttime' => now()->format('H:i'),
                'tapstate' => $tapstate,
                'studid' => $studentId,
                'createddatetime' => now(),
                'createdby' => auth()->id() ?? null,
                'tapstatus' => 1,
                'deleted' => 0
            ]);
        }
    }
    // Optional: Get recent scans for dashboard
    public function getRecentScans()
    {
        $scans = DB::table('scan_logs')
            ->where('status', 'success')
            ->orderBy('scan_time', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $scans
        ]);
    }
}