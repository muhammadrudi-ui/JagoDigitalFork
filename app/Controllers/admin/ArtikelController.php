<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ArtikelModel;
use App\Models\KategoriModel;

class ArtikelController extends BaseController
{
    protected $artikelModel;
    protected $kategoriModel;

    public function __construct()
    {
        $this->artikelModel = new ArtikelModel();
        $this->kategoriModel = new KategoriModel();
    }

    public function index()
    {
        // Mengambil semua data artikel dari tabel tb_artikel
        $all_data_artikel = $this->artikelModel->orderBy('created_at', 'DESC')->findAll();

        // Mengirimkan data artikel ke view
        $data = [
            'all_data_artikel' => $all_data_artikel,
        ];

        return view('/admin/artikel/index', $data);
    }

    public function create()
    {
        // Mengambil semua data kategori dari model 'KategoriModel'
        $kategori = $this->kategoriModel->findAll();

        // Menyiapkan data untuk dikirim ke view
        $data = [
            'kategori' => $kategori
        ];

        // Menampilkan view dengan data kategori
        return view('/admin/artikel/tambah', $data);
    }

    public function store()
    {
        // Validasi input
        if (!$this->validate([
            'judul_artikel' => 'required',
            'kategori' => 'required|integer',
            'foto_artikel' => 'uploaded[foto_artikel]|mime_in[foto_artikel,image/jpg,image/jpeg,image/png]|max_size[foto_artikel,2048]',
            'deskripsi_artikel' => 'required',
            'tags' => 'required'
        ])) {
            return redirect()->back()->withInput()->with('error', 'Validasi gagal.');
        }

        // Handle file upload
        $fotoArtikel = $this->request->getFile('foto_artikel');
        $fotoArtikelName = $fotoArtikel->getRandomName();
        $fotoArtikel->move('uploads/upload_artikel', $fotoArtikelName);

        // Buat slug otomatis dari judul dan tambahkan tanggal ddmmyyyy
        $judul = $this->request->getVar('judul_artikel');
        $tanggal = date('dmY'); // Format tanggal ddmmyyyy
        $slug = url_title($judul, '-', true) . '-' . $tanggal; // Menghasilkan slug + tanggal

        // Insert data artikel
        $this->artikelModel->save([
            'id_kategori' => $this->request->getVar('kategori'),
            'judul_artikel' => $judul,
            'foto_artikel' => $fotoArtikelName,
            'deskripsi_artikel' => $this->request->getVar('deskripsi_artikel'),
            'tags' => $this->request->getVar('tags'),
            'slug' => $slug,
            'views' => 0, // Set default views to 0
            'created_at' => date('Y-m-d H:i:s') // Format waktu yang benar untuk created_at
        ]);

        // Redirect dengan pesan sukses
        return redirect()->to(base_url('admin/artikel/index'))->with('success', 'Artikel berhasil ditambahkan.');
    }



    public function edit($id)
    {
        // Mengambil artikel berdasarkan ID
        $artikel = $this->artikelModel->find($id);

        // Jika artikel tidak ditemukan, kembalikan dengan pesan error
        if (!$artikel) {
            return redirect()->to('/admin/artikel/index')->with('error', 'Artikel tidak ditemukan.');
        }

        // Mengambil semua data kategori dari tabel 'kategori'
        $kategori = $this->kategoriModel->findAll();

        // Menyiapkan data untuk dikirim ke view
        $data = [
            'artikel' => $artikel,
            'kategori' => $kategori
        ];

        // Menampilkan view dengan data artikel dan kategori
        return view('/admin/artikel/edit', $data);
    }

    public function update($id)
    {
        $model = new ArtikelModel();
        $artikel = $model->find($id);

        // Inisialisasi array $data untuk menyimpan data yang akan diupdate
        $data = [];

        // Mendapatkan file yang diunggah
        $file = $this->request->getFile('foto_artikel');

        // Mengecek apakah ada file yang diunggah dan valid
        if ($file && $file->isValid() && !$file->hasMoved()) {
            // Validasi jenis file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']; // Daftar tipe file yang diizinkan
            if (in_array($file->getMimeType(), $allowedTypes)) {
                // Nama file baru
                $newFileName = $file->getRandomName();

                // Pindahkan file ke folder uploads
                $file->move('uploads/upload_artikel/', $newFileName);

                // Tambahkan nama file baru ke data yang akan diupdate
                $data['foto_artikel'] = $newFileName;
            } else {
                return redirect()->back()->with('error', 'Format file tidak diizinkan.');
            }
        }

        // Buat slug otomatis dari judul dan tambahkan tanggal ddmmyyyy
        $judul = $this->request->getVar('judul_artikel');
        $tanggal = date('dmY'); // Format tanggal ddmmyyyy
        $slug = url_title($judul, '-', true) . '-' . $tanggal; // Menghasilkan slug + tanggal

        // Mendapatkan data dari input form
        $data['judul_artikel'] = $this->request->getPost('judul_artikel');
        $data['id_kategori'] = $this->request->getPost('id_kategori');
        $data['deskripsi_artikel'] = $this->request->getPost('deskripsi_artikel');
        $data['tags'] = $this->request->getPost('tags');
        $data['slug'] = $slug; // Gunakan slug otomatis
        

        // Memastikan data tidak kosong sebelum melakukan update
        if (!empty($data)) {
            $model->update($id, $data);
            return redirect()->to('/admin/artikel/index')->with('success', 'Artikel berhasil diperbarui.');
        } else {
            return redirect()->back()->with('error', 'Tidak ada data yang diubah.');
        }
    }


    public function delete($id_artikel)
    {
        // Temukan artikel berdasarkan ID
        $artikel = $this->artikelModel->find($id_artikel);

        if ($artikel) {
            // Hapus file gambar jika ada
            $pathToFile = 'uploads/upload_artikel/' . $artikel['foto_artikel'];
            if (!empty($artikel['foto_artikel']) && file_exists($pathToFile)) {
                if (!unlink($pathToFile)) {
                    // Jika file tidak bisa dihapus, mungkin ingin menambahkan log atau penanganan kesalahan.
                    return redirect()->to(base_url('admin/artikel/index'))->with('error', 'Gagal menghapus file gambar.');
                }
            }

            // Hapus data artikel dari database
            if (!$this->artikelModel->delete($id_artikel)) {
                // Jika penghapusan data dari database gagal
                return redirect()->to(base_url('admin/artikel/index'))->with('error', 'Gagal menghapus artikel.');
            }

            return redirect()->to(base_url('admin/artikel/index'))->with('success', 'Artikel berhasil dihapus.');
        } else {
            return redirect()->to(base_url('admin/artikel/index'))->with('error', 'Artikel tidak ditemukan.');
        }
    }
}
