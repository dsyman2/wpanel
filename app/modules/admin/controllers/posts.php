<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Este é o controller de categorias, usado principalmente
| no painel de controle do site.
|
| @author Eliel de Paula <elieldepaula@gmail.com>
| @since 21/10/2014
|--------------------------------------------------------------------------
*/

class Posts extends MX_Controller {

	function __construct()
	{
		$this->auth->protect('admin');
		$this->form_validation->set_error_delimiters('<p><span class="label label-danger">', '</span></p>');
	}

	public function index()
	{
		$this->load->model('post');
		$this->load->library('table');

		$layout_vars = array();
		$content_vars = array();

		// Template da tabela
		$this->table->set_template(array('table_open'  => '<table id="grid" class="table table-striped">')); 
		$this->table->set_heading('#', 'Título', 'Categoria(s)', 'Data', 'Status', 'Ações');
		$query = $this->post->get_by_field('page','0', array('field'=>'created','order'=>'desc'));//, array('offset'=>'0','limit'=>'2'));

		foreach($query->result() as $row)
		{
			$this->table->add_row(
				$row->id, 
				$row->title, 
				$this->wpanel->categorias_do_post($row->id),
				mdate('%d/%m/%Y', strtotime($row->created)), 
				status_post($row->status),
				// Ícones de ações
				div(array('class'=>'btn-group btn-group-sm')).
				anchor('admin/posts/edit/'.$row->id, glyphicon('edit'), array('class' => 'btn btn-default')).
				'<button class="btn btn-default" onClick="return confirmar(\''.site_url('admin/posts/delete/' . $row->id).'\');">'.glyphicon('trash').'</button>' .
				div(null,true)

				);
		}

		$content_vars['listagem'] = $this->table->generate();
		$this->wpanel->load_view('posts/index', $content_vars);
	}

	public function add()
	{
		
		$layout_vars = array();
		$content_vars = array();

		$this->form_validation->set_rules('title', 'Título', 'required');
		
		if ($this->form_validation->run() == FALSE)
		{
			$this->load->model('categoria');
			// Prepara a lista de categorias.
			$query = $this->categoria->get_list();
			$categorias = array();
			foreach($query->result() as $row){
				$categorias[$row->id] = $row->title;
			}

			$content_vars['categorias'] = $categorias;
			$this->wpanel->load_view('posts/add', $content_vars);

		} else {

			$this->load->model('post');

			$dados_save = array();
			$dados_save['user_id'] = $this->auth->get_userid();
			$dados_save['title'] = $this->input->post('title');
			$dados_save['description'] = $this->input->post('description');
			$dados_save['link'] = strtolower(url_title(convert_accented_characters($this->input->post('title'))));
			$dados_save['content'] = $this->input->post('content');
			$dados_save['tags'] = $this->input->post('tags');
			$dados_save['status'] = $this->input->post('status');
			$dados_save['created'] = date('Y-m-d H:i:s');
			$dados_save['updated'] = date('Y-m-d H:i:s');
			$dados_save['image'] = $this->upload();
			// Identifica se é uma página ou uma postagem
			// 0=post, 1=Página
			$dados_save['page'] = '0';

			$new_post = $this->post->save($dados_save);

			if($new_post)
			{
				// Salva o relacionamento das categorias
				$this->load->model('post_categoria');

				foreach($this->input->post('category_id') as $cat_id){
					$cat_save = array();
					$cat_save['post_id'] = $new_post;
					$cat_save['category_id'] = $cat_id;
					$this->post_categoria->save($cat_save);
				}

				$this->session->set_flashdata('msg_sistema', 'Postagem salva com sucesso.');
				redirect('admin/posts');
			} else {
				$this->session->set_flashdata('msg_sistema', 'Erro ao salvar a postagem.');
				redirect('admin/posts');
			}

		}
	}

	public function edit($id = null)
	{
		$layout_vars = array();
		$content_vars = array();

		$this->form_validation->set_rules('title', 'Título', 'required');
		
		if ($this->form_validation->run() == FALSE)
		{

			if($id == null){
				$this->session->set_flashdata('msg_sistema', 'Postagem inexistente.');
				redirect('admin/posts');
			}

			$this->load->model('post_categoria');
			$this->load->model('categoria');
			$this->load->model('post');

			// Prepara a lista de categorias.
			$query = $this->categoria->get_list();
			$categorias = array();
			foreach($query->result() as $row){
				$categorias[$row->id] = $row->title;
			}

			// Prepara as categorias selecionadas.
			$query = $this->post_categoria->get_by_field('post_id', $id);
			$cat_select = array();
			foreach($query->result() as $x => $row){
				$cat_select[$x] = $row->category_id;
			}

			$content_vars['id'] = $id;
			$content_vars['categorias'] = $categorias;
			$content_vars['cat_select'] = $cat_select;
			$content_vars['row'] = $this->post->get_by_id($id)->row();
			$this->wpanel->load_view('posts/edit', $content_vars);

		} else {

			$this->load->model('post');

			$dados_save = array();
			$dados_save['title'] = $this->input->post('title');
			$dados_save['description'] = $this->input->post('description');
			$dados_save['link'] = strtolower(url_title(convert_accented_characters($this->input->post('title'))));
			$dados_save['content'] = $this->input->post('content');
			$dados_save['tags'] = $this->input->post('tags');
			$dados_save['status'] = $this->input->post('status');
			$dados_save['updated'] = date('Y-m-d H:i:s');
			// Identifica se é uma página ou uma postagem
			// 0=post, 1=Página
			$dados_save['page'] = '0';
			
			if($this->input->post('alterar_imagem')=='1')
			{
				$this->remove_image($id);
				$dados_save['image'] = $this->upload();
			}

			$upd_post = $this->post->update($id, $dados_save);

			if($upd_post)
			{
				// Salva o relacionamento das categorias.
				$this->load->model('post_categoria');
				// Apaga os relacionamentos anteriores.
				$this->post_categoria->delete_by_post($id);
				// Cadastra as alterações.
				foreach($this->input->post('category_id') as $cat_id){
					$cat_save = array();
					$cat_save['post_id'] = $id;
					$cat_save['category_id'] = $cat_id;
					$this->post_categoria->save($cat_save);
				}

				$this->session->set_flashdata('msg_sistema', 'Postagem salva com sucesso.');
				redirect('admin/posts');
			} else {
				$this->session->set_flashdata('msg_sistema', 'Erro ao salvar a postagem.');
				redirect('admin/posts');
			}

		}
	}

	public function delete($id = null)
	{

		if($id == null){
			$this->session->set_flashdata('msg_sistema', 'Postagem inexistente.');
			redirect('admin/posts');
		}

		$this->load->model('post');

		$this->remove_image($id);

		if($this->post->delete($id)){
			$this->session->set_flashdata('msg_sistema', 'Postagem excluída com sucesso.');
			redirect('admin/posts');
		} else {
			$this->session->set_flashdata('msg_sistema', 'Erro ao excluir a postagem.');
			redirect('admin/posts');
		}
	}

	private function upload()
	{

		$config['upload_path'] = './media/capas/';
		$config['allowed_types'] = 'gif|jpg|png';
		$config['max_size']	= '1000';
		$config['max_width']  = '0';
		$config['max_height']  = '0';
		$config['remove_spaces'] = TRUE;
		$config['file_name'] = md5(date('YmdHis'));

		$this->load->library('upload', $config);

		if ($this->upload->do_upload())
		{
			$upload_data = array();
			$upload_data = $this->upload->data();
			return $upload_data['file_name'];
		} else {
			return false;
		}

	}

	/**
	 * Este método faz a exclusão de uma imagem de capa.
	 *
	 * @return boolean
	 * @param $id Integer ID da postagem.
	 * @author Eliel de Paula <elieldepaula@gmail.com>
	 **/
	private function remove_image($id)
    {
    	$this->load->model('post');
        $post = $this->post->get_by_id($id)->row();
        $filename = './media/capas/' . $post->image;
        if(file_exists($filename))
        {
            if(unlink($filename))
            {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

}