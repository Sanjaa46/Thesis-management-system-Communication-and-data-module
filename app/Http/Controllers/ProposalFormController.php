<?php

namespace App\Http\Controllers;

use App\Models\ProposalForm;
use Illuminate\Http\Request;
use Log;

class ProposalFormController extends Controller
{
    public function index()
    {
        // dep_id shalgana
        return ProposalForm::all();
    }

    // Store a new proposal form in storage
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|string|max:10|unique:proposal_forms',
            'dep_id' => 'required|string|max:10|exists:departments,id',
            'fields' => 'required|array',
            'default_fields' => 'nullable|array',
            'created_date' => 'required|date',
            'created_by' => 'nullable|string',
        ]);

        try {
            $proposalForm = ProposalForm::create([
                'id' => $validatedData['id'],
                'dep_id' => $validatedData['dep_id'],
                'fields' => $validatedData['fields'],
                'default_fields' => $validatedData['default_fields'] ?? [],
                'created_date' => $validatedData['created_date'],
                'created_by' => $validatedData['created_by'],
            ]);

            return response()->json([
                'message' => 'Proposal form saved successfully',
                'data' => $proposalForm
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to save proposal form',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($id)
    {
        return ProposalForm::findOrFail($id);
    }

    // Update an existing proposal form in storage
    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required',
            'dep_id' => 'required|string|max:10|exists:departments,id',
            'fields' => 'required|array',
            'default_fields' => 'nullable|array',
            'created_date' => 'required|date',
            'created_by' => 'nullable|string',
        ]);

        try {
            $proposalForm = ProposalForm::findOrFail($validatedData['id']);
            $proposalForm->update([
                'dep_id' => $validatedData['dep_id'],
                'fields' => $validatedData['fields'],
                'default_fields' => $validatedData['default_fields'] ?? [],
                'created_date' => $validatedData['created_date'],
                'created_by' => $validatedData['created_by'],
            ]);

            return response()->json([
                'message' => 'Proposal form updated successfully',
                'data' => $proposalForm
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update proposal form',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function destroy($id)
    {
        $proposalForm = ProposalForm::findOrFail($id);
        $proposalForm->delete();

        return response()->json(['message' => 'Proposal form deleted successfully']);
    }
}
