<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Konsultasi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        // untuk mengecek status login
        checking_session($this->username, $this->role, ['admin']);
    }

    public function index()
    {
        $data = [
            'kriteria' => $this->m_kriteria->list_jenis('input')->result(),
        ];

        $this->template->load('admin', 'Konsultasi', 'konsultasi', 'view', $data);
    }

    public function process()
    {
        $post = $this->input->post(NULL, TRUE);

        $id_kriteria = $post['id_kriteria'];
        $value       = $post['value'];

        $nilai = [];
        foreach ($id_kriteria as $key => $row) {
            $nilai[$row] = $value[$key];
        }

        // =========================================================================

        $data = $this->m_kriteria->list_jenis('input')->result_array();

        $konsultasi = [];
        $kriteria = [];
        foreach ($data as $key => $value) {
            $konsultasi[] = [
                'id_kriteria' => $value['id_kriteria'],
                'nama'        => $value['nama'],
                'nilai'       => $nilai[$value['id_kriteria']]
            ];

            $kriteria[$value['id_kriteria']] = $value['nama'];
        }

        $fuzzifikasi = [];
        foreach ($konsultasi as $key => $value) {
            $fuzzifikasi[$value['id_kriteria']] = $this->fuzzytsukamoto->fuzzy_dynamic($value['nilai'], $value['id_kriteria']);
        }

        // =========================================================================

        $data2 = $this->m_rule->list()->result_array();

        $inferensi = [];
        $currentRule = [];
        foreach ($data2 as $key => $row) {
            if ($row['kondisi'] == 'if') {
                $currentRule[] = $row;
            } elseif ($row['kondisi'] == 'then') {
                $currentRule[] = $row;
                $inferensi[] = $currentRule;
                $currentRule = [];
            }
        }

        $rules = [];
        foreach ($inferensi as $key => $rule) {
            $hasil    = '';
            $keys     = array_keys($rule);
            $firstKey = $keys[0];
            $lastKey  = $keys[count($keys) - 1];

            foreach ($rule as $key => $item) {
                if ($key == $firstKey) {
                    // Jika data pertama
                    $hasil .= "<b>JIKA</b> " . $item['kriteria'] . " => " . $item['skala'] . " ";
                } elseif ($key == $lastKey) {
                    // Jika data terakhir
                    $hasil .= "<b>MAKA</b> " . $item['skala'];
                } else {
                    // Selain itu (di tengah)
                    $hasil .= "<b>DAN</b> " . $item['kriteria'] . " => " . $item['skala'] . " ";
                }
            }

            $rules[] = $hasil;
        }

        // =========================================================================

        $hasil_inferensi = [];
        foreach ($inferensi as $key => $rule) {
            $hitung = [];
            $bobot  = '';

            foreach ($rule as $key => $value) {
                if ($value['kondisi'] == 'if') {
                    $bobot = strtolower($value['skala']);
                    $hitung[] = $fuzzifikasi[$value['id_kriteria']][$bobot];
                }

                if ($value['kondisi'] == 'then') {
                    $bobot = strtolower($value['skala']);
                }
            }

            $hasil_inferensi[] = [
                'hasil' => min($hitung),
                'rule'  => $bobot
            ];
        }

        // =========================================================================

        $predikat = [];
        $pembagi  = [];
        $hitung_inferensi = [];
        foreach ($hasil_inferensi as $key => $rule) {
            $predikat[] = $rule['hasil'] * $this->fuzzytsukamoto->fuzzy_inferensi($rule['hasil'], $rule['rule']);
            $pembagi[]  = $rule['hasil'];

            $hitung_inferensi[] = $this->fuzzytsukamoto->fuzzy_inferensi($rule['hasil'], $rule['rule']);
        }

        $defuzzifikasi = (array_sum($predikat) / array_sum($pembagi));

        // =========================================================================

        $get_input  = $this->m_kriteria->list_jenis('input')->result_array();
        $get_output = $this->m_kriteria->list_jenis('output')->result_array();

        $input_string = [];
        foreach ($get_input as $key => $value) {
            $input_string[] = $value['nama'] . ' => ' . $nilai[$value['id_kriteria']];
        }

        $output_string = [];
        foreach ($get_output as $key => $value) {
            $output_string[] = $value['nama'] . ' => ' . $defuzzifikasi;
        }

        $inputStr   = implode(' DAN ', $input_string);
        $outputStr  = implode(' DAN ', $output_string);
        $kesimpulan = "JIKA $inputStr MAKA $outputStr";

        $data = [
            'konsultasi'       => $konsultasi,
            'kriteria'         => $kriteria,
            'fuzzifikasi'      => $fuzzifikasi,
            'rules'            => $rules,
            'hasil_inferensi'  => $hasil_inferensi,
            'hitung_inferensi' => $hitung_inferensi,
            'defuzzifikasi'    => $defuzzifikasi,
            'kesimpulan'       => $kesimpulan
        ];

        $this->template->load('admin', 'Hasil Konsultasi', 'konsultasi', 'result', $data);
    }
}
