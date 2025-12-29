import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Edit2, Trash2, Search, MessageSquare, Loader2 } from "lucide-react";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { useToast } from "@/hooks/use-toast";
import { getMessages, createMessage, updateMessage, deleteMessage } from "@/lib/api";

interface Template {
  id: string;
  name: string;
  content: string;
  createdAt: string;
  usageCount?: number;
  source?: string;
  templateCode?: string;
}

export default function Mensagens() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<Template | null>(null);
  const [formData, setFormData] = useState({ name: "", content: "" });

  const { data: messages = [], isLoading } = useQuery({
    queryKey: ['messages'],
    queryFn: getMessages,
  });

  // Mapeia os dados da API para o formato esperado
  const templates: Template[] = messages.map((msg: any) => ({
    id: String(msg.id),
    name: msg.title || '',
    content: msg.content || '',
    createdAt: new Date(msg.date).toLocaleDateString('pt-BR'),
    usageCount: 0, // Não temos esse dado na API ainda
    source: msg.source || 'local',
    templateCode: msg.template_code || msg.template_id || '',
  }));

  const filteredTemplates = templates.filter((t) =>
    t.name.toLowerCase().includes(search.toLowerCase())
  );

  const createMutation = useMutation({
    mutationFn: (data: { title: string; content: string }) => createMessage(data),
    onSuccess: () => {
      toast({ title: "Template criado com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['messages'] });
      setIsOpen(false);
      setFormData({ name: "", content: "" });
      setEditingTemplate(null);
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao criar template",
        description: error.message || "Erro ao criar template",
        variant: "destructive",
      });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: { title: string; content: string } }) =>
      updateMessage(id, data),
    onSuccess: () => {
      toast({ title: "Template atualizado com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['messages'] });
      setIsOpen(false);
      setFormData({ name: "", content: "" });
      setEditingTemplate(null);
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao atualizar template",
        description: error.message || "Erro ao atualizar template",
        variant: "destructive",
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteMessage(id),
    onSuccess: () => {
      toast({ title: "Template excluído com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['messages'] });
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao excluir template",
        description: error.message || "Erro ao excluir template",
        variant: "destructive",
      });
    },
  });

  const handleSave = () => {
    if (!formData.name.trim() || !formData.content.trim()) {
      toast({
        title: "Campos obrigatórios",
        description: "Nome e conteúdo são obrigatórios",
        variant: "destructive",
      });
      return;
    }

    if (editingTemplate) {
      updateMutation.mutate({
        id: editingTemplate.id,
        data: { title: formData.name, content: formData.content },
      });
    } else {
      createMutation.mutate({ title: formData.name, content: formData.content });
    }
  };

  const handleEdit = (template: Template) => {
    setEditingTemplate(template);
    setFormData({ name: template.name, content: template.content });
    setIsOpen(true);
  };

  const handleDelete = (id: string) => {
    deleteMutation.mutate(id);
  };

  const openNewDialog = () => {
    setEditingTemplate(null);
    setFormData({ name: "", content: "" });
    setIsOpen(true);
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Templates de Mensagem"
        description="Gerencie os templates usados nas campanhas"
      >
        <Button onClick={openNewDialog} className="gradient-primary hover:opacity-90">
          <Plus className="mr-2 h-4 w-4" />
          Novo Template
        </Button>
      </PageHeader>

      {/* Search */}
      <Card>
        <CardContent className="p-4">
          <div className="relative max-w-md">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar templates..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-10"
            />
          </div>
        </CardContent>
      </Card>

      {/* Templates Grid */}
      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <Skeleton key={i} className="h-48" />
          ))}
        </div>
      ) : filteredTemplates.length === 0 ? (
        <div className="text-center py-12">
          <MessageSquare className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
          <h3 className="text-lg font-semibold">Nenhum template encontrado</h3>
          <p className="text-muted-foreground">Crie seu primeiro template para começar</p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filteredTemplates.map((template, index) => (
            <Card
              key={template.id}
              className="animate-scale-in hover:shadow-md transition-shadow"
              style={{ animationDelay: `${index * 50}ms` }}
            >
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-2">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                      <MessageSquare className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <CardTitle className="text-base">{template.name}</CardTitle>
                        {template.source === 'otima_wpp' && (
                          <Badge variant="outline" className="text-xs">Ótima WPP</Badge>
                        )}
                        {template.source === 'otima_rcs' && (
                          <Badge variant="outline" className="text-xs">Ótima RCS</Badge>
                        )}
                      </div>
                      <CardDescription className="text-xs">{template.createdAt}</CardDescription>
                      {template.templateCode && (
                        <CardDescription className="text-xs text-muted-foreground">
                          Código: {template.templateCode}
                        </CardDescription>
                      )}
                    </div>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground line-clamp-3">{template.content}</p>
                <div className="flex items-center justify-between">
                  {template.usageCount !== undefined && (
                    <span className="text-xs text-muted-foreground">
                      Usado {template.usageCount} vezes
                    </span>
                  )}
                  <div className="flex gap-1">
                    {template.source === 'local' && (
                      <>
                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => handleEdit(template)}>
                          <Edit2 className="h-4 w-4" />
                        </Button>
                        <AlertDialog>
                          <AlertDialogTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:text-destructive">
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>Excluir template?</AlertDialogTitle>
                              <AlertDialogDescription>
                                Esta ação não pode ser desfeita. O template "{template.name}" será removido permanentemente.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                              <AlertDialogCancel>Cancelar</AlertDialogCancel>
                              <AlertDialogAction
                                onClick={() => handleDelete(template.id)}
                                className="bg-destructive hover:bg-destructive/90"
                                disabled={deleteMutation.isPending}
                              >
                                {deleteMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Excluir
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>
                      </>
                    )}
                    {(template.source === 'otima_wpp' || template.source === 'otima_rcs') && (
                      <Badge variant="secondary" className="text-xs">
                        Somente leitura
                      </Badge>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Dialog */}
      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingTemplate ? "Editar Template" : "Novo Template"}</DialogTitle>
            <DialogDescription>
              {editingTemplate
                ? "Atualize as informações do template"
                : "Crie um novo template de mensagem"}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="name">Nome do Template</Label>
              <Input
                id="name"
                placeholder="Ex: Promoção de Natal"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="content">Conteúdo da Mensagem</Label>
              <Textarea
                id="content"
                placeholder="Digite sua mensagem..."
                value={formData.content}
                onChange={(e) => setFormData({ ...formData, content: e.target.value })}
                rows={5}
              />
              <p className="text-xs text-muted-foreground">
                Variáveis: {"{nome}"}, {"{cpf}"}, {"{telefone}"}, {"{email}"}, {"{link}"}, {"{data}"}
              </p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsOpen(false)} disabled={createMutation.isPending || updateMutation.isPending}>
              Cancelar
            </Button>
            <Button 
              onClick={handleSave} 
              className="gradient-primary hover:opacity-90"
              disabled={createMutation.isPending || updateMutation.isPending}
            >
              {(createMutation.isPending || updateMutation.isPending) && (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              )}
              {editingTemplate ? "Salvar Alterações" : "Criar Template"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
