<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contacts = Contact::all();

        return response()->json([
            'message' => 'Datos de contacto recuperados con éxito.',
            'contacts' => $contacts
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:contacts,name',
            'icon' => 'required|string|max:255',
            'url' => 'required|string|max:255',
        ]);

        $contact = Contact::create([
            'name' => $request->name,
            'icon' => $request->icon,
            'url' => $request->url,
        ]);

        return response()->json([
            'message' => 'Dato de contacto creado con éxito.',
            'contact' => $contact
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact)
    {
        return response()->json([
            'contact' => $contact
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:contacts,name,' . $contact->id,
            'icon' => 'required|string|max:255',
            'url' => 'required|string|max:255',
        ]);

        $contact->update([
            'name' => $request->name,
            'icon' => $request->icon,
            'url' => $request->url,
        ]);

        return response()->json([
            'message' => 'Dato de contacto actualizado con éxito.',
            'contact' => $contact
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact)
    {
        $contact->delete();

        return response()->json([
            'message' => 'Dato de contacto eliminado con éxito.'
        ]);
    }
}
