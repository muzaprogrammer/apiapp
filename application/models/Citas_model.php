<?php
defined('BASEPATH') OR exit('No direct script access Allowed');

class Citas_model extends CI_model {

	public function __construct() {
		parent::__construct();
		$this->load->database();
	}

	public function seguridad($user,$pass) {
		$query = $this->db->query("
			SELECT * FROM usuarios WHERE usuario = '$user' AND password = '$pass'
		");
		if ($query->num_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function buscar_pacientes($text) {
		$sql="SELECT idexpediente, nombre_completo FROM expedientes WHERE estado=0 ";
		$bus=explode(" ", $text);
		foreach ($bus as $key => $value) {
			$sql.="AND concat_ws(' ',nombres,apellidos) LIKE '%".$value."%'";
		}
		$sql.="ORDER BY idexpediente DESC LIMIT 0,500";
		$pacientes = $this->db->query($sql)->result_array();
		return $pacientes;
	}

	public function sucursales() {
		$sucursales = $this->db->query("
			SELECT idsucursal, sucursal FROM sucursales
		")->result_array();
		return $sucursales;
	}

	public function especialidades($idsucursal) {
		$especialidades = $this->db->query("
			SELECT
				es.id_especialidad, es.especialidad
			FROM
				medicos me
				INNER JOIN especialidades es ON es.id_especialidad = me.id_especialidad
			WHERE
				me.idsucursal = $idsucursal
				AND me.estado = 0
				AND me.app = 1
				AND es.estado = 1
				AND es.app = 1
			GROUP BY
				id_especialidad
		")->result_array();
		return $especialidades;
	}

	public function medicos($idsucursal,$especialidad) {
		$medicos = $this->db->query("
			SELECT
				idmedico, CONCAT(me.nombres, ' ', me.apellidos) AS 'nombre_medico'
			FROM
				medicos me
				INNER JOIN especialidades es ON es.id_especialidad = me.id_especialidad
			WHERE
			  	me.idsucursal = $idsucursal
			  	AND es.id_especialidad = $especialidad
				AND me.estado = 0
				AND me.app = 1
				AND es.estado = 1
				AND es.app = 1
		")->result_array();
		return $medicos;
	}

	public function procedimientos($idmedico) {
		$procedimientos = $this->db->query("
			SELECT
				procedimientos_medicos_especialidades.id_procedimiento_medico_especialidad, procedimientos_medicos_especialidades.periodo_minimo, TIME_FORMAT(procedimientos_medicos_especialidades.periodo_minimo, '%H:%i') AS 'periodo', procedimientos_medicos.nombre_procedimiento
			FROM
				medicos
				INNER JOIN procedimientos_medicos_especialidades ON procedimientos_medicos_especialidades.id_especialidad = medicos.id_especialidad
				INNER JOIN procedimientos_medicos ON procedimientos_medicos.id_procedimiento_medico = procedimientos_medicos_especialidades.id_procedimiento_medico
			WHERE
				medicos.idmedico = $idmedico AND procedimientos_medicos_especialidades.estado = 1;
		")->result_array();
		return $procedimientos;
	}
}
